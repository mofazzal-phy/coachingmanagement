<?php

namespace Modules\Attendance\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Attendance\app\Services\AttendanceEngine;
use Modules\Attendance\app\Services\AttendanceAnalytics;
use Modules\Attendance\app\Services\BiometricMappingService;
use Modules\Attendance\app\Services\ClassSessionService;
use Modules\Attendance\app\Services\SessionAttendanceService;
use Modules\Attendance\app\Services\Biometric\BiometricServiceManager;
use Modules\Attendance\Console\Commands\SyncClassSessionsCommand;

class AttendanceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Attendance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'attendance';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SyncClassSessionsCommand::class,
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
     * Register the module services.
     */
    public function register(): void
    {
        parent::register();

        // Register BiometricServiceManager as singleton
        $this->app->singleton(BiometricServiceManager::class, function ($app) {
            $manager = new BiometricServiceManager();
            return $manager;
        });

        // Register AttendanceEngine as singleton
        $this->app->singleton(AttendanceEngine::class, function ($app) {
            return new AttendanceEngine();
        });

        // Register AttendanceAnalytics as singleton
        $this->app->singleton(AttendanceAnalytics::class, function ($app) {
            return new AttendanceAnalytics();
        });

        $this->app->singleton(BiometricMappingService::class, function ($app) {
            return new BiometricMappingService();
        });

        $this->app->singleton(ClassSessionService::class, function ($app) {
            return new ClassSessionService();
        });

        $this->app->singleton(SessionAttendanceService::class, function ($app) {
            return new SessionAttendanceService($app->make(ClassSessionService::class));
        });
    }

    /**
     * Boot the module services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
