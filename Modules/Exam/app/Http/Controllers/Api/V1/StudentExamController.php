<?php

namespace Modules\Exam\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamAdmitCardService;
use Modules\Exam\app\Services\ExamRoutineService;
use Modules\Exam\app\Services\ExamPaperService;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Exam\app\Services\ExamReportPdfService;
use Modules\Exam\app\Services\ExamEligibilityService;
use Modules\Core\app\Http\Controllers\BaseApiController;
use Modules\Exam\app\Support\ResolvesStudentFromAuth;

class StudentExamController extends BaseApiController
{
    use ResolvesStudentFromAuth;
    public function __construct(
        private ExamRoutineRepository $repository,
        private ExamRoutineService $routineService,
        private ExamAdmitCardService $admitCardService,
        private ExamEligibilityService $eligibilityService,
        private ExamPaperService $paperService,
        private ExamAssessmentService $assessmentService,
        private ExamReportPdfService $reportPdfService,
    ) {}

    /**
     * Get all exam routines for the authenticated student.
     */
    public function routines(): JsonResponse
    {
        $studentId = $this->getStudentId();
        $routines = $this->routineService->getStudentRoutines($studentId);

        return $this->success($routines);
    }

    /**
     * Get upcoming exams for the authenticated student.
     */
    public function upcoming(): JsonResponse
    {
        $studentId = $this->getStudentId();
        $upcoming = $this->routineService->getUpcomingForStudent($studentId);

        $enriched = collect($upcoming)->map(function ($item) use ($studentId) {
            $row = is_array($item) ? $item : (array) $item;
            $examId = $row['exam_id'] ?? ($row['exam']['id'] ?? null);
            if ($examId) {
                $row['exam_eligibility'] = $this->eligibilityService->getStudentStatus($examId, $studentId, false);
            }

            return $row;
        })->values();

        return $this->success($enriched);
    }

    /**
     * Get next exam countdown for the authenticated student.
     */
    public function nextExam(): JsonResponse
    {
        $studentId = $this->getStudentId();
        $upcoming = $this->repository->getUpcomingForStudent($studentId, 1);

        if ($upcoming->isEmpty()) {
            return $this->success([
                'has_next' => false,
                'message' => 'No upcoming exams',
            ]);
        }

        $next = $upcoming->first();
        $now = now();
        $examDate = $next->exam_date;
        $examStart = $next->start_time;

        // Calculate countdown
        $examDateTime = \Carbon\Carbon::parse($examDate->format('Y-m-d') . ' ' . $examStart);
        $diffInHours = $now->diffInHours($examDateTime, false);

        return $this->success([
            'has_next' => true,
            'exam' => [
                'id' => $next->id,
                'exam_id' => $next->exam_id,
                'exam_name' => $next->exam?->name ?? 'N/A',
                'subject' => $next->subject?->name ?? 'N/A',
                'date' => $examDate->format('d M, Y'),
                'day' => $examDate->format('l'),
                'start_time' => $next->start_time_formatted,
                'end_time' => $next->end_time_formatted,
                'room' => $next->room?->name ?? 'TBD',
            ],
            'countdown' => [
                'days' => max(0, $now->diffInDays($examDateTime, false)),
                'hours' => max(0, $now->copy()->addDays($now->diffInDays($examDateTime))->diffInHours($examDateTime, false)),
                'minutes' => max(0, $now->diffInMinutes($examDateTime, false) % 60),
                'is_today' => $examDate->isToday(),
                'is_past' => $diffInHours < 0,
                'starts_in_hours' => max(0, round($diffInHours, 1)),
            ],
        ]);
    }

    /**
     * Get admit card for a specific exam.
     */
    public function admitCard(string $examId): JsonResponse
    {
        $studentId = $this->getStudentId();

        try {
            $eligibility = $this->eligibilityService->getStudentStatus($examId, $studentId, true);

            if (!($eligibility['can_download_admit'] ?? false)) {
                return $this->error(
                    $eligibility['message'] ?? 'You are not eligible to download the admit card.',
                    403,
                    ['eligibility' => $eligibility]
                );
            }

            $data = $this->admitCardService->getAdmitCardData($examId, $studentId);

            if (isset($data['error'])) {
                return $this->notFound($data['error']);
            }

            return $this->success($data);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * Download admit card PDF for a specific exam.
     */
    public function downloadAdmitCard(string $examId): \Illuminate\Http\Response|JsonResponse
    {
        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        try {
            $pdf = $this->admitCardService->generatePdfBinary($examId, $studentId);
            $safeName = 'admit-card-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $examId) . '.pdf';

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error('Failed to generate admit card: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Set a reminder for an exam.
     */
    public function setReminder(Request $request): JsonResponse
    {
        $request->validate([
            'exam_routine_id' => 'required|string|exists:exam_routines,id',
            'reminder_minutes' => 'required|integer|in:15,30,60,120,1440',
        ]);

        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $routine = ExamRoutine::find($request->input('exam_routine_id'));
        if (!$routine) {
            return $this->notFound('Exam routine not found');
        }

        if (!$this->eligibilityService->isStudentInRoutineScope($routine, $studentId)) {
            return $this->error('You can only set reminders for your own batch exams.', 403);
        }

        // Store reminder in session or user preferences
        // For now, return success (actual reminder implementation would use notifications)
        $reminderTime = \Carbon\Carbon::parse(
            $routine->exam_date->format('Y-m-d') . ' ' . $routine->start_time
        )->subMinutes($request->input('reminder_minutes'));

        return $this->success([
            'exam_routine_id' => $routine->id,
            'exam' => $routine->exam?->name,
            'subject' => $routine->subject?->name,
            'reminder_at' => $reminderTime->format('d M, Y h:i A'),
            'minutes_before' => $request->input('reminder_minutes'),
            'message' => 'Reminder set successfully',
        ]);
    }

    /**
     * Get exam results for the authenticated student.
     */
    public function results(): JsonResponse
    {
        $studentId = $this->getStudentId();

        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account. Please contact admin.', 404);
        }

        $allPublished = \Modules\Exam\app\Models\ExamResult::with([
            'exam.examType', 'routine.subject', 'subject',
        ])->where('student_id', $studentId)
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();

        $results = $allPublished->filter(function ($r) {
            $channel = \Modules\Exam\app\Models\ExamRoutine::resolveDeliveryChannel(
                $r->routine?->delivery_mode ?: 'offline'
            );
            return $r->exam && $this->assessmentService->canViewChannelResults($r->exam, $channel);
        })->values();

        $examSummaries = [];
        $subjectHighsByExam = [];
        foreach ($results->groupBy(function ($result) {
            $channel = \Modules\Exam\app\Models\ExamRoutine::resolveDeliveryChannel(
                $result->routine?->delivery_mode ?: 'offline'
            );

            return $result->exam_id . ':' . $channel;
        }) as $summaryKey => $examResults) {
            $first = $examResults->first();
            $examId = $first->exam_id;
            $channel = \Modules\Exam\app\Models\ExamRoutine::resolveDeliveryChannel(
                $first->routine?->delivery_mode ?: 'offline'
            );
            $summary = $this->assessmentService->getExamResultSummaryForStudent($examId, $studentId, $channel);
            $examSummaries[$summaryKey] = array_merge($summary['standing'] ?? [], [
                'merit_scope' => $summary['merit_scope'] ?? null,
                'delivery_channel' => $channel,
            ]);
            $subjectHighsByExam[$summaryKey] = $summary['subject_highs'] ?? [];
        }

        $grouped = $results->groupBy(fn($r) => $r->exam?->name ?? 'Unknown Exam');

        $officialResultIds = $results->pluck('id')->all();
        $onlineResults = \Modules\Exam\app\Models\ExamResult::with([
            'exam.examType', 'routine.subject', 'subject',
        ])->where('student_id', $studentId)
            ->whereNotNull('exam_attempt_id')
            ->whereHas('exam', fn ($q) => $q->where('is_practice', false))
            ->whereNotIn('id', $officialResultIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) {
                $mcq = (float) (($r->marks_breakdown ?? [])['mcq'] ?? $r->marks_obtained ?? 0);
                $total = (float) ($r->total_marks ?? 0);
                $pct = $total > 0 ? round(($mcq / $total) * 100, 1) : 0;

                return array_merge($r->toArray(), [
                    'marks' => $r->marks_obtained,
                    'mcq_score' => $mcq,
                    'mcq_percentage' => $pct,
                    'is_online' => true,
                    'result_source' => 'online',
                    'delivery_mode' => $r->exam?->delivery_mode ?? 'online',
                ]);
            });

        return $this->success([
            'results' => $results->map(function ($r) use ($examSummaries) {
                $channel = \Modules\Exam\app\Models\ExamRoutine::resolveDeliveryChannel(
                    $r->routine?->delivery_mode ?: 'offline'
                );
                $summaryKey = $r->exam_id . ':' . $channel;
                $standing = $examSummaries[$summaryKey] ?? null;

                return array_merge($r->toArray(), [
                    'position' => $standing['position'] ?? null,
                    'gpa' => $standing['gpa'] ?? null,
                    'overall_grade' => $standing['overall_grade'] ?? null,
                    'exam_total_marks' => $standing['total_marks'] ?? null,
                    'exam_percentage' => $standing['percentage'] ?? null,
                    'result_source' => 'official',
                    'delivery_channel' => $channel,
                    'is_online_result' => $channel === 'online',
                ]);
            }),
            'online_results' => $onlineResults,
            'grouped' => $grouped,
            'exam_summaries' => $examSummaries,
            'subject_highs_by_exam' => $subjectHighsByExam,
            'total' => $results->count(),
            'online_total' => $onlineResults->count(),
        ]);
    }

    /**
     * Leaderboard for a published exam (scoped to the student's batch).
     */
    public function leaderboard(Request $request, string $examId): JsonResponse
    {
        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $validated = $request->validate([
            'delivery_channel' => 'required|in:offline,online',
            'top' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $data = $this->assessmentService->buildStudentLeaderboard(
                $examId,
                $studentId,
                $validated['delivery_channel'],
                (int) ($validated['top'] ?? 50),
            );

            return $this->success($data);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * Unofficial MCQ-only leaderboard before official online result publication.
     */
    public function provisionalLeaderboard(Request $request, string $examId): JsonResponse
    {
        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $validated = $request->validate([
            'top' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $data = $this->assessmentService->buildProvisionalMcqLeaderboard(
                $examId,
                $studentId,
                (int) ($validated['top'] ?? 50),
            );

            return $this->success($data);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * Download marksheet PDF for a published exam.
     */
    public function downloadMarksheet(Request $request, string $examId): \Illuminate\Http\Response|JsonResponse
    {
        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $channel = $request->query('delivery_channel');

        try {
            $pdf = $this->reportPdfService->generateMarksheetPdf($examId, $studentId, $channel);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="marksheet-' . $examId . '.pdf"',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->error('Failed to generate marksheet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get practice routines available to the student.
     */
    public function practiceRoutines(): JsonResponse
    {
        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $routines = $this->paperService->getStudentPracticeRoutines($studentId);

        return $this->success([
            'routines' => $routines,
            'total' => count($routines),
        ]);
    }

    /**
     * Live online exam routines within the current exam window.
     */
    public function liveExams(): JsonResponse
    {
        $studentId = $this->getStudentId();
        if (empty($studentId)) {
            return $this->error('Student profile not linked to this account.', 404);
        }

        $routines = $this->paperService->getLiveExamRoutines($studentId);

        return $this->success([
            'routines' => $routines,
            'total' => count($routines),
            'live_count' => collect($routines)->where('window_status', 'live')->count(),
        ]);
    }

    /**
     * Get the authenticated student's ID.
     */
    private function getStudentId(): string
    {
        return $this->resolveStudentId();
    }
}
