<?php

namespace Modules\Exam\app\Console;

use Illuminate\Console\Command;
use Modules\Exam\app\Models\Exam;

class RepairExamPublishState extends Command
{
    protected $signature = 'exam:repair-publish-state';

    protected $description = 'Publish exams that have published routines but exam status is still draft';

    public function handle(): int
    {
        $exams = Exam::where('status', 'draft')
            ->whereHas('routines', fn ($q) => $q->where('status', 'published'))
            ->get();

        if ($exams->isEmpty()) {
            $this->info('No exams need repair.');

            return self::SUCCESS;
        }

        foreach ($exams as $exam) {
            $exam->update(['status' => 'published']);
            $this->line("Published exam: {$exam->name} ({$exam->id})");
        }

        $this->info("Repaired {$exams->count()} exam(s).");

        return self::SUCCESS;
    }
}
