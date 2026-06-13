<?php

namespace Modules\Exam\app\Console;

use Illuminate\Console\Command;
use Modules\Exam\app\Services\ExamPaperService;

class AutoSubmitExpiredExamAttempts extends Command
{
    protected $signature = 'exam:auto-submit-expired';

    protected $description = 'Auto-submit in-progress official online exam attempts after the exam window ends';

    public function handle(ExamPaperService $paperService): int
    {
        $result = $paperService->autoSubmitExpiredAttempts();

        $this->info("Auto-submitted: {$result['submitted']}, failed: {$result['failed']}");

        return self::SUCCESS;
    }
}
