<?php

namespace Modules\Cms\app\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Cms\app\Services\CmsAuditLogService;

class PublishScheduledCmsContent extends Command
{
    protected $signature = 'cms:publish-scheduled';

    protected $description = 'Publish CMS content whose scheduled_at time has arrived';

    public function handle(CmsAuditLogService $auditLogService): int
    {
        $tables = config('cms.publishable_tables', []);
        $published = 0;

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'scheduled_at')) {
                continue;
            }

            $query = DB::table($table)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', now());

            if (Schema::hasColumn($table, 'published_at')) {
                $query->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '>', now());
                });
            }

            $rows = $query->get(['id', 'scheduled_at']);

            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update([
                    'published_at' => now(),
                ]);

                if (Schema::hasColumn($table, 'status')) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->where('status', 'draft')
                        ->update(['status' => 'published']);
                }

                $auditLogService->log(
                    $table,
                    $row->id,
                    'auto_publish',
                    'Content auto-published by scheduler',
                    ['scheduled_at' => $row->scheduled_at],
                    ['published_at' => now()->toDateTimeString()]
                );

                $published++;
            }
        }

        $this->info("Published {$published} scheduled CMS item(s).");

        return self::SUCCESS;
    }
}
