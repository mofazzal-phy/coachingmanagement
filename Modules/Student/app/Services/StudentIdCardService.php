<?php

namespace Modules\Student\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Core\app\Services\QrCodeService;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Settings\app\Models\Setting;
use Modules\Student\app\Models\Student;

class StudentIdCardService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function getIdCardData(string $studentId, ?string $enrollmentId = null): array
    {
        $student = Student::with(['currentClass', 'user'])->findOrFail($studentId);

        $enrollment = null;
        if ($enrollmentId) {
            $enrollment = Enrollment::with(['batch.course', 'academicSession'])
                ->where('student_id', $studentId)
                ->find($enrollmentId);
        }
        if (!$enrollment) {
            $enrollment = Enrollment::with(['batch.course', 'academicSession'])
                ->where('student_id', $studentId)
                ->where('status', 'active')
                ->latest()
                ->first();
        }

        $qrPayload = json_encode([
            'type' => 'student',
            'id' => $student->id,
            'student_id' => $student->student_id,
        ]);

        return [
            'institution' => $this->institution(),
            'person' => [
                'type' => 'Student',
                'name' => $student->user?->name ?? trim($student->first_name . ' ' . $student->last_name),
                'id_label' => 'Student ID',
                'id_number' => $student->student_id ?? 'N/A',
                'roll_no' => $student->roll_no,
                'class' => $student->currentClass?->name ?? $enrollment?->batch?->course?->name,
                'batch' => $enrollment?->batch?->name,
                'session' => $enrollment?->academicSession?->name,
                'phone' => $student->phone,
                'photo_url' => $this->photoUrl($student->photo),
            ],
            'qr_base64' => $this->qrCodeService->generateBase64($qrPayload, 200),
            'generated_at' => now()->format('d M, Y'),
        ];
    }

    public function generatePdfBinary(string $studentId, ?string $enrollmentId = null): string
    {
        $data = $this->getIdCardData($studentId, $enrollmentId);

        return Pdf::loadView('student::id-card', $data)
            ->setPaper([0, 0, 242.65, 153.07], 'portrait')
            ->output();
    }

    protected function institution(): array
    {
        return [
            'name' => Setting::where('key', 'institute_name')->value('value')
                ?? Setting::where('key', 'site_name')->value('value')
                ?? config('app.name', 'Coaching Management System'),
            'address' => Setting::where('key', 'institute_address')->value('value') ?? '',
        ];
    }

    protected function photoUrl(?string $photo): ?string
    {
        if (!$photo) {
            return null;
        }
        if (str_starts_with($photo, 'http')) {
            return $photo;
        }

        return asset('storage/' . ltrim($photo, '/'));
    }
}
