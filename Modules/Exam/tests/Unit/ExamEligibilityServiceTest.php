<?php

namespace Modules\Exam\Tests\Unit;

use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Services\ExamEligibilityService;
use Tests\TestCase;

class ExamEligibilityServiceTest extends TestCase
{
    public function test_resolve_status_threshold_boundaries(): void
    {
        $service = app(ExamEligibilityService::class);
        $exam = new Exam([
            'eligibility_check_enabled' => true,
            'is_practice' => false,
            'min_attendance_percent' => 75,
        ]);

        $eligible = $service->resolveStatus(75.0, $exam);
        $this->assertSame('eligible', $eligible['status']);
        $this->assertTrue($eligible['can_download_admit']);

        $warning = $service->resolveStatus(74.9, $exam);
        $this->assertSame('warning', $warning['status']);
        $this->assertTrue($warning['can_download_admit']);

        $blocked = $service->resolveStatus(59.9, $exam);
        $this->assertSame('blocked', $blocked['status']);
        $this->assertFalse($blocked['can_download_admit']);
    }

    public function test_check_disabled_when_practice_exam(): void
    {
        $service = app(ExamEligibilityService::class);
        $exam = new Exam([
            'eligibility_check_enabled' => true,
            'is_practice' => true,
        ]);

        $this->assertFalse($service->isCheckEnabled($exam));
    }
}
