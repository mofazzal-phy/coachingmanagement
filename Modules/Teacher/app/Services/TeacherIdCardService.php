<?php

namespace Modules\Teacher\app\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Core\app\Services\QrCodeService;
use Modules\Settings\app\Models\Setting;
use Modules\Teacher\app\Models\Teacher;

class TeacherIdCardService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}

    public function getIdCardData(string $teacherId): array
    {
        $teacher = Teacher::with(['user', 'academicGroup'])->findOrFail($teacherId);

        $qrPayload = json_encode([
            'type' => 'teacher',
            'id' => $teacher->id,
            'teacher_id' => $teacher->teacher_id,
        ]);

        return [
            'institution' => $this->institution(),
            'person' => [
                'type' => 'Teacher',
                'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
                'id_label' => 'Teacher ID',
                'id_number' => $teacher->teacher_id ?? 'N/A',
                'roll_no' => null,
                'class' => $teacher->specialization,
                'batch' => $teacher->academicGroup?->name,
                'session' => null,
                'phone' => $teacher->phone,
                'photo_url' => $this->photoUrl($teacher->photo),
            ],
            'qr_base64' => $this->qrCodeService->generateBase64($qrPayload, 200),
            'generated_at' => now()->format('d M, Y'),
        ];
    }

    public function generatePdfBinary(string $teacherId): string
    {
        $data = $this->getIdCardData($teacherId);

        return Pdf::loadView('teacher::id-card', $data)
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
