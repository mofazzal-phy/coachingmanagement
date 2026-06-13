<?php

namespace Modules\Exam\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ExamServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Exam';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'exam';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\Exam\app\Console\AutoSubmitExpiredExamAttempts::class,
        \Modules\Exam\app\Console\RepairExamPublishState::class,
        \Modules\Exam\app\Console\SeedPracticeExamDemo::class,
        \Modules\Exam\app\Console\FixBatchExamOverlaps::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('exam:auto-submit-expired')->everyMinute();
    }
}
