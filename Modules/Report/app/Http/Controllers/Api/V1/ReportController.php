<?php

namespace Modules\Report\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Core\app\Services\GradingService;
use Modules\Academic\app\Models\Classes;
use Modules\Academic\app\Models\AcademicSession;
use Modules\Student\app\Models\Student;
use Modules\Finance\app\Models\FeeCollection;
use Modules\Finance\app\Models\Expense;
use Modules\Hr\app\Models\Employee;
use Modules\Hr\app\Models\Payroll;
use Modules\Attendance\app\Models\Attendance;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Exam\app\Services\ExamReportPdfService;

class ReportController extends BaseApiController
{
    public function __construct(
        private readonly GradingService $gradingService,
        private readonly ExamAssessmentService $assessmentService,
        private readonly ExamReportPdfService $reportPdfService,
    ) {
    }

    public function dashboard(): JsonResponse
    {
        $totalStudents = Student::count();
        $totalEmployees = Employee::count();
        $totalClasses = Classes::count();
        $activeSessions = AcademicSession::where('is_current', true)->first();

        $monthlyFeeCollected = FeeCollection::whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year)
            ->whereIn('status', ['paid', 'partial'])
            ->sum('paid_amount');

        $monthlyExpenses = Expense::whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        $monthlyPayroll = Payroll::where('month', now()->format('Y-m'))
            ->where('status', 'paid')
            ->sum('net_salary');

        $todayAttendance = Attendance::whereDate('date', today())->count();
        $todayPresent = Attendance::whereDate('date', today())->where('status', 'present')->count();

        return $this->success([
            'total_students' => $totalStudents,
            'total_employees' => $totalEmployees,
            'total_classes' => $totalClasses,
            'current_session' => $activeSessions?->name,
            'monthly_fee_collected' => (float) $monthlyFeeCollected,
            'monthly_expenses' => (float) $monthlyExpenses,
            'monthly_payroll' => (float) $monthlyPayroll,
            'today_attendance' => $todayAttendance,
            'today_present' => $todayPresent,
        ]);
    }

    public function studentReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|string|exists:classes,id',
            'section_id' => 'sometimes|string|exists:sections,id',
        ]);

        $students = Student::where('current_class_id', $validated['class_id'])
            ->when($validated['section_id'] ?? null, fn($q, $v) => $q->where('current_section_id', $v))
            ->with(['guardian', 'attendances', 'feeCollections'])
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'student_id' => $student->student_id,
                    'roll_no' => $student->roll_no,
                    'total_present' => $student->attendances->where('status', 'present')->count(),
                    'total_absent' => $student->attendances->where('status', 'absent')->count(),
                    'total_fees_paid' => $student->feeCollections->whereIn('status', ['paid', 'partial'])->sum('paid_amount'),
                    'total_due' => $student->feeCollections->whereIn('status', ['unpaid', 'partial', 'overdue'])->sum('due_amount'),
                ];
            });

        return $this->collectionResponse($students);
    }

    public function financialReport(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month');

        $query = FeeCollection::whereYear('created_at', $year);
        $expenseQuery = Expense::whereYear('expense_date', $year);

        if ($month) {
            $query->whereMonth('created_at', $month);
            $expenseQuery->whereMonth('expense_date', $month);
        }

        $totalIncome = (float) $query->whereIn('status', ['paid', 'partial'])->sum('paid_amount');
        $totalExpense = (float) $expenseQuery->sum('amount');
        $totalDiscount = (float) FeeCollection::whereYear('created_at', $year)
            ->when($month, fn($q) => $q->whereMonth('created_at', $month))
            ->sum('discount');

        return $this->success([
            'year' => $year,
            'month' => $month,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'total_discount' => $totalDiscount,
            'net_profit' => $totalIncome - $totalExpense,
        ]);
    }

    public function examReport(string $examId): JsonResponse
    {
        $exam = Exam::with(['class', 'section', 'routines.subject', 'results.student'])->find($examId);
        if (!$exam) return $this->notFound();

        $results = ExamResult::where('exam_id', $examId)
            ->with('student')
            ->get()
            ->groupBy('student_id')
            ->map(function ($studentResults) {
                $totalMarks = $studentResults->sum('marks_obtained');
                $totalPossible = $studentResults->sum('total_marks');
                $percentage = $totalPossible > 0 ? round(($totalMarks / $totalPossible) * 100, 2) : 0;
                return [
                    'student' => $studentResults->first()->student,
                    'total_marks' => (float) $totalMarks,
                    'total_possible' => (float) $totalPossible,
                    'percentage' => $percentage,
                    'grade' => $this->gradingService->calculateGradeLetter($percentage),
                    'subjects' => $studentResults,
                ];
            })->values();

        return $this->success([
            'exam' => $exam,
            'results' => $results,
        ]);
    }

    public function examMerit(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'scope' => 'sometimes|in:batch,course,class,all',
            'scope_id' => 'sometimes|string',
            'top' => 'sometimes|integer|min:0|max:500',
            'delivery_channel' => 'sometimes|in:offline,online',
        ]);

        [$scopeType, $scopeId] = $this->resolveMeritScopeParams(
            $exam,
            $examId,
            $validated['scope'] ?? 'all',
            $validated['scope_id'] ?? null,
        );

        $top = (int) ($validated['top'] ?? 20);

        $channel = $validated['delivery_channel'] ?? 'offline';

        if (!$this->assessmentService->canViewChannelResults($exam, $channel)) {
            return $this->error(ucfirst($channel) . ' results are not published yet.', 403);
        }

        return $this->success($this->assessmentService->computeMeritList($examId, $scopeType, $scopeId, $top, $channel));
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveMeritScopeParams(
        Exam $exam,
        string $examId,
        string $scopeType,
        ?string $scopeId,
    ): array {
        if ($scopeType === 'all') {
            return [null, null];
        }

        if ($scopeType === 'batch' && !$scopeId) {
            $routineBatchIds = \Modules\Exam\app\Models\ExamRoutine::where('exam_id', $examId)
                ->whereNotNull('batch_id')
                ->pluck('batch_id')
                ->unique()
                ->values();

            if ($routineBatchIds->count() === 1) {
                return ['batch', (string) $routineBatchIds->first()];
            }

            if ($exam->batch_id) {
                return ['batch', $exam->batch_id];
            }

            return [null, null];
        }

        if ($scopeType === 'course' && !$scopeId) {
            $scopeId = $exam->course_id;
            if (!$scopeId) {
                return [null, null];
            }

            return ['course', $scopeId];
        }

        if ($scopeType === 'class' && !$scopeId) {
            $scopeId = $exam->class_id;
            if (!$scopeId) {
                return [null, null];
            }

            return ['class', $scopeId];
        }

        return [$scopeType, $scopeId];
    }

    public function examAnalytics(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'batch_id' => 'sometimes|string|exists:batches,id',
        ]);

        return $this->success($this->assessmentService->getExamAnalytics($examId, $validated['batch_id'] ?? null));
    }

    public function examSubjectAnalysis(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'batch_id' => 'sometimes|string|exists:batches,id',
        ]);

        return $this->success($this->assessmentService->getSubjectAnalysis($examId, $validated['batch_id'] ?? null));
    }

    public function examExport(Request $request, string $examId): Response|JsonResponse
    {
        $exam = Exam::find($examId);
        if (!$exam) {
            return $this->notFound();
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,csv',
            'type' => 'sometimes|in:result_sheet,merit_list',
            'batch_id' => 'sometimes|string|exists:batches,id',
            'scope' => 'sometimes|in:batch,course,class,all',
            'scope_id' => 'sometimes|string',
            'top' => 'sometimes|integer|min:0|max:500',
            'delivery_channel' => 'sometimes|in:offline,online',
        ]);

        $format = $validated['format'];
        $type = $validated['type'] ?? 'result_sheet';
        $batchId = $validated['batch_id'] ?? null;
        $channel = $validated['delivery_channel'] ?? 'offline';

        try {
            if ($format === 'csv') {
                $csv = $this->reportPdfService->generateResultSheetCsv($examId, $batchId, $channel);

                return response($csv, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="result-sheet-' . $examId . '.csv"',
                ]);
            }

            if ($type === 'merit_list') {
                [$scope, $scopeId] = $this->resolveMeritScopeParams(
                    $exam,
                    $examId,
                    $validated['scope'] ?? 'all',
                    $validated['scope_id'] ?? null,
                );

                if (!$this->assessmentService->canViewChannelResults($exam, $channel)) {
                    throw new \InvalidArgumentException(
                        ucfirst($channel) . ' results are not available for export yet.'
                    );
                }

                $top = (int) ($validated['top'] ?? 20);
                $merit = $this->assessmentService->computeMeritList($examId, $scope, $scopeId, $top, $channel);

                if (($merit['total_students'] ?? 0) === 0) {
                    throw new \InvalidArgumentException(
                        'No published results for this channel and scope. Switch Offline/Online channel or scope, then try again.'
                    );
                }

                $pdf = $this->reportPdfService->generateMeritListPdf($examId, $scope, $scopeId, $top, $channel);
                $filename = 'merit-list-' . $channel . '-' . $examId . '.pdf';
            } else {
                $pdf = $this->reportPdfService->generateResultSheetPdf($examId, $batchId, $channel);
                $filename = 'result-sheet-' . $examId . '.pdf';
            }

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError([], $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to generate export: ' . $e->getMessage(), 500);
        }
    }

}
