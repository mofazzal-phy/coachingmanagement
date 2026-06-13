<?php

namespace Modules\Enrollment\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class EnrollmentServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Enrollment';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'enrollment';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        \Modules\Enrollment\app\Console\SendMonthlyFeeReminders::class,
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
        // Send monthly fee reminders on the 25th of every month at 8 AM
        $schedule->command('enrollment:send-monthly-fee-reminders')
            ->monthlyOn(25, '08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/monthly-fee-reminders.log'));
    }
}
