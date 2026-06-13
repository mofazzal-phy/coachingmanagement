<?php

namespace Modules\Exam\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Settings\app\Models\Setting;
use Modules\Student\app\Models\Student;

class ExamAdmitCardService
{
    public function __construct(
        private readonly ExamEligibilityService $eligibilityService,
    ) {}

    /**
     * Generate admit card data for a student in an exam.
     */
    public function getAdmitCardData(string $examId, string $studentId): array
    {
        $exam = Exam::with(['examType', 'batch', 'course', 'class', 'session'])->findOrFail($examId);
        $eligibility = $this->eligibilityService->getStudentStatus($examId, $studentId, true);

        $student = Student::with('user')->find($studentId);
        if (!$student) {
            return ['error' => 'Student not found'];
        }

        $enrollment = $this->resolveStudentEnrollment($exam, $studentId);

        $batchName = $enrollment?->batch?->name;
        $courseName = $enrollment?->batch?->course?->name ?? $exam->course?->name;
        $sessionName = $enrollment?->academicSession?->name ?? $exam->session?->name;

        return [
            'institution' => [
                'name' => Setting::where('key', 'institute_name')->value('value')
                    ?? Setting::where('key', 'site_name')->value('value')
                    ?? config('app.name', 'Coaching Management System'),
                'address' => Setting::where('key', 'institute_address')->value('value') ?? '',
            ],
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_type' => ['name' => $exam->examType?->name ?? 'N/A'],
                'type' => $exam->examType?->name ?? 'N/A',
                'session' => $sessionName,
                'start_date' => $exam->start_date?->toDateString(),
                'end_date' => $exam->end_date?->toDateString(),
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name ?? trim($student->first_name . ' ' . $student->last_name),
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_id' => $student->student_id ?? $student->id_number ?? 'N/A',
                'roll_no' => $student->roll_no,
                'photo_url' => $this->studentPhotoUrl($student),
                'batch' => $batchName ? ['name' => $batchName] : null,
                'course' => $courseName ? ['name' => $courseName] : null,
                'class' => $exam->class ? ['name' => $exam->class->name] : null,
            ],
            'generated_at' => now()->format('d M, Y h:i A'),
            'eligibility' => $eligibility,
        ];
    }

    protected function resolveStudentEnrollment(Exam $exam, string $studentId): ?Enrollment
    {
        $batchIds = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->filter()
            ->unique()
            ->values();

        $primaryBatchId = $this->resolvePrimaryBatchId($exam, $batchIds);

        if ($primaryBatchId) {
            $enrollment = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->where('batch_id', $primaryBatchId)
                ->with(['batch.course', 'academicSession'])
                ->first();
            if ($enrollment) {
                return $enrollment;
            }
        }

        return Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->when($batchIds->isNotEmpty(), fn ($q) => $q->whereIn('batch_id', $batchIds->all()))
            ->with(['batch.course', 'academicSession'])
            ->first();
    }

    /**
     * PDF binary for direct download (no public storage URL).
     */
    public function generatePdfBinary(string $examId, string $studentId): string
    {
        $this->eligibilityService->assertCanDownloadAdmitCard($examId, $studentId);

        $data = $this->getAdmitCardData($examId, $studentId);
        if (isset($data['error'])) {
            throw new \RuntimeException($data['error']);
        }

        return Pdf::loadView('exam::admit-card', $this->formatForPdfView($data))->output();
    }

    /**
     * @deprecated Use generatePdfBinary(); kept for bulk generation.
     */
    public function generatePdf(string $examId, string $studentId): string
    {
        $binary = $this->generatePdfBinary($examId, $studentId);
        $filename = 'admit-card-' . $examId . '-' . $studentId . '.pdf';
        $path = 'admit-cards/' . $filename;
        Storage::put($path, $binary);

        return $path;
    }

    /**
     * @return array{0: Collection<int, ExamRoutine>, 1: Enrollment|null}
     */
    protected function resolveStudentExamRoutines(Exam $exam, string $studentId): array
    {
        $batchIds = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->pluck('batch_id')
            ->filter()
            ->unique()
            ->values();

        $primaryBatchId = $this->resolvePrimaryBatchId($exam, $batchIds);

        $query = ExamRoutine::with(['subject', 'room', 'batch'])
            ->where('exam_id', $exam->id)
            ->published()
            ->orderBy('exam_date')
            ->orderBy('start_time');

        if ($primaryBatchId) {
            $query->where('batch_id', $primaryBatchId);
        } elseif ($batchIds->isNotEmpty()) {
            $query->whereIn('batch_id', $batchIds->all());
        }

        $routines = $query->get()->unique(function ($routine) {
            $date = $routine->exam_date?->format('Y-m-d') ?? '';
            $start = $this->normalizeTimeKey($routine->start_time);

            return ($routine->subject_id ?? $routine->id) . '|' . $date . '|' . $start;
        })->values();

        $enrollment = null;
        if ($primaryBatchId) {
            $enrollment = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->where('batch_id', $primaryBatchId)
                ->with(['batch.course', 'academicSession'])
                ->first();
        }

        if (!$enrollment && $batchIds->isNotEmpty()) {
            $enrollment = Enrollment::query()
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->whereIn('batch_id', $batchIds->all())
                ->with(['batch.course', 'academicSession'])
                ->first();
        }

        return [$routines, $enrollment];
    }

    /**
     * @param  Collection<int, string>  $batchIds
     */
    protected function resolvePrimaryBatchId(Exam $exam, Collection $batchIds): ?string
    {
        if ($batchIds->isEmpty()) {
            return $exam->batch_id;
        }

        if ($exam->batch_id && $batchIds->contains($exam->batch_id)) {
            return $exam->batch_id;
        }

        $bestBatch = ExamRoutine::query()
            ->published()
            ->where('exam_id', $exam->id)
            ->whereIn('batch_id', $batchIds->all())
            ->selectRaw('batch_id, COUNT(*) as routine_count')
            ->groupBy('batch_id')
            ->orderByDesc('routine_count')
            ->first();

        return $bestBatch?->batch_id ?? $batchIds->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapRoutineRow(ExamRoutine $routine): array
    {
        $roomLabel = $routine->room?->name
            ?? $routine->room?->room_number
            ?? 'Will be announced';

        return [
            'id' => $routine->id,
            'exam_date' => $routine->exam_date?->toDateString(),
            'day' => $routine->exam_date?->format('l'),
            'start_time' => $routine->start_time_formatted ?? $this->formatTime12($routine->start_time),
            'end_time' => $routine->end_time_formatted ?? $this->formatTime12($routine->end_time),
            'subject' => $routine->subject ? [
                'name' => $routine->subject->name,
                'code' => $routine->subject->code ?? '',
            ] : null,
            'room' => [
                'name' => $roomLabel,
                'room_number' => $routine->room?->room_number,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function formatForPdfView(array $data): array
    {
        $student = $data['student'] ?? [];
        $exam = $data['exam'] ?? [];

        return [
            'institution' => $data['institution'] ?? ['name' => config('app.name')],
            'exam' => [
                'name' => $exam['name'] ?? 'Exam',
                'type' => $exam['exam_type']['name'] ?? $exam['type'] ?? 'N/A',
                'session' => $exam['session'] ?? '',
                'start_date' => $this->formatDatePdf($exam['start_date'] ?? null),
                'end_date' => $this->formatDatePdf($exam['end_date'] ?? null),
            ],
            'student' => [
                'name' => $student['name'] ?? 'N/A',
                'student_id' => $student['student_id'] ?? 'N/A',
                'roll_no' => $student['roll_no'] ?? '—',
                'batch' => $student['batch']['name'] ?? '—',
                'course' => $student['course']['name'] ?? '—',
                'photo' => $student['photo_url'] ?? null,
            ],
            'generated_at' => $data['generated_at'] ?? now()->format('d M, Y h:i A'),
            'eligibility' => $data['eligibility'] ?? null,
        ];
    }

    protected function studentPhotoUrl(Student $student): ?string
    {
        if (!$student->photo) {
            return null;
        }

        if (str_starts_with($student->photo, 'http')) {
            return $student->photo;
        }

        return asset('storage/' . ltrim($student->photo, '/'));
    }

    protected function normalizeTimeKey(mixed $time): string
    {
        if (!$time) {
            return '';
        }

        return substr((string) $time, 0, 5);
    }

    protected function formatTime12(mixed $time): string
    {
        if (!$time) {
            return '—';
        }

        $parts = explode(':', (string) $time);
        $h = (int) ($parts[0] ?? 0);
        $m = $parts[1] ?? '00';
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $hour = $h % 12 ?: 12;

        return sprintf('%d:%s %s', $hour, $m, $ampm);
    }

    protected function formatDatePdf(?string $date): string
    {
        if (!$date) {
            return 'TBD';
        }

        return \Carbon\Carbon::parse($date)->format('d M, Y');
    }

    /**
     * Generate admit card PDF for all students in an exam.
     */
    public function generateBulkPdf(string $examId): array
    {
        $exam = Exam::findOrFail($examId);
        $students = $this->eligibilityService->studentsForExam($exam);

        $generated = [];
        foreach ($students as $student) {
            try {
                $path = $this->generatePdf($examId, $student->id);
                $generated[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->user?->name ?? trim($student->first_name . ' ' . $student->last_name),
                    'path' => $path,
                ];
            } catch (\Exception $e) {
                $generated[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->user?->name ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'exam_id' => $examId,
            'total' => count($generated),
            'successful' => count(array_filter($generated, fn ($g) => !isset($g['error']))),
            'failed' => count(array_filter($generated, fn ($g) => isset($g['error']))),
            'generated' => $generated,
        ];
    }
}
