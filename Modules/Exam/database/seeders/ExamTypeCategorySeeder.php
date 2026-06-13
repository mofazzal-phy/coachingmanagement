<?php

namespace Modules\Exam\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Exam\app\Models\ExamType;

class ExamTypeCategorySeeder extends Seeder
{
    /**
     * Exam types = paper format (MCQ / CQ / Both), not Daily/Weekly names.
     * Daily, Weekly, Model Test etc. belong in exams.name field.
     */
    public function run(): void
    {
        $formats = [
            [
                'name' => 'MCQ',
                'code' => 'MCQ',
                'category' => 'paper_format',
                'description' => 'Multiple choice questions only',
            ],
            [
                'name' => 'CQ',
                'code' => 'CQ',
                'category' => 'paper_format',
                'description' => 'Creative / written questions only',
            ],
            [
                'name' => 'MCQ + CQ',
                'code' => 'BOTH',
                'category' => 'paper_format',
                'description' => 'Combined MCQ and CQ paper',
            ],
        ];

        foreach ($formats as $type) {
            ExamType::updateOrCreate(
                ['code' => $type['code']],
                array_merge($type, ['status' => 'active'])
            );
        }

        // Retire old category-based types (Daily Test, Model Test, etc.)
        ExamType::whereIn('code', ['DAILY', 'WEEKLY', 'MODEL', 'MOCK', 'FINAL'])
            ->update(['status' => 'inactive']);
    }
}
