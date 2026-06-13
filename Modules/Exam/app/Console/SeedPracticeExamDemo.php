<?php

namespace Modules\Exam\app\Console;

use Illuminate\Console\Command;
use Modules\Exam\Database\Seeders\PracticeExamDemoSeeder;

class SeedPracticeExamDemo extends Command
{
    protected $signature = 'exam:seed-practice-demo';

    protected $description = 'Seed a published demo practice exam with MCQ questions for the Practice Center';

    public function handle(): int
    {
        $seeder = new PracticeExamDemoSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        return self::SUCCESS;
    }
}
