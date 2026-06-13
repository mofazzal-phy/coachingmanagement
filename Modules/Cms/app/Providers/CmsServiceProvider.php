<?php

namespace Modules\Cms\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Cms\app\Console\PublishScheduledCmsContent;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CmsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Cms';

    protected string $nameLower = 'cms';

    protected array $commands = [
        PublishScheduledCmsContent::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('cms:publish-scheduled')->everyFiveMinutes();
    }
}
