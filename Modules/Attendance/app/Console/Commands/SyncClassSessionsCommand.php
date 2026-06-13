<?php

namespace Modules\Attendance\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Attendance\app\Services\ClassSessionService;

class SyncClassSessionsCommand extends Command
{
    protected $signature = 'attendance:sync-class-sessions
                            {date? : Date to sync (Y-m-d), defaults to today}
                            {--batch= : Optional batch UUID to limit sync}';

    protected $description = 'Generate class sessions from published routines for a date';

    public function handle(ClassSessionService $service): int
    {
        $date = Carbon::parse($this->argument('date') ?? now()->toDateString());
        $batchIds = $this->option('batch') ? [$this->option('batch')] : null;

        $result = $service->syncForDate($date, $batchIds);

        $this->info("Synced class sessions for {$date->toDateString()}");
        $this->line("  Created: {$result['created']}");
        $this->line("  Updated: {$result['updated']}");
        $this->line("  Total:   {$result['total']}");

        return self::SUCCESS;
    }
}
