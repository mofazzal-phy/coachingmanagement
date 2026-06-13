<?php

namespace Modules\Attendance\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Attendance\Events\AttendanceMarked;
use Modules\Attendance\Listeners\CheckAttendanceEligibility;
use Modules\Attendance\Listeners\SendAttendanceNotification;
use Modules\Attendance\Listeners\UpdateAttendanceAnalytics;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        AttendanceMarked::class => [
            UpdateAttendanceAnalytics::class,
            SendAttendanceNotification::class,
            CheckAttendanceEligibility::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
