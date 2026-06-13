<?php

namespace Modules\Exam\Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\app\Models\Batch;
use Modules\Enrollment\app\Models\Enrollment;
use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Models\ExamType;
use Modules\Exam\app\Models\Question;
use Modules\Exam\app\Services\ExamPaperService;

class PracticeExamDemoSeeder extends Seeder
{
    private const DEMO_EXAM_NAME = 'Demo Practice Center';

    /** @var array<string, string> */
    private const DEMO_SUBJECTS = [
        'Physics' => 'e1edec17-dc3f-4769-a2d0-6f482a2d9a68',
        'Chemistry' => '63cff9f1-0b45-4cef-8fc3-43a394c86885',
    ];

    public function run(): void
    {
        $context = $this->resolveContext();
        if (!$context) {
            $this->command?->error('No active enrollment with batch found. Enroll at least one student first.');

            return;
        }

        $admin = User::role(['super-admin', 'admin'])->first();
        $examType = ExamType::where('code', 'MCQ')->orWhere('name', 'MCQ')->first()
            ?? ExamType::first();

        if (!$examType) {
            $this->command?->error('No exam type found. Run ExamTypeCategorySeeder first.');

            return;
        }

        DB::transaction(function () use ($context, $admin, $examType) {
            $exam = Exam::firstOrCreate(
                [
                    'name' => self::DEMO_EXAM_NAME,
                    'batch_id' => $context['batch_id'],
                    'is_practice' => true,
                ],
                [
                    'exam_type_id' => $examType->id,
                    'academic_session_id' => $context['session_id'],
                    'class_id' => $context['class_id'],
                    'course_id' => $context['course_id'],
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->addMonths(3)->toDateString(),
                    'description' => 'Auto-seeded demo practice exam for student portal testing.',
                    'delivery_mode' => 'offline',
                    'is_practice' => true,
                    'eligibility_check_enabled' => false,
                    'status' => 'draft',
                ]
            );

            $exam->update([
                'academic_session_id' => $context['session_id'],
                'class_id' => $context['class_id'],
                'course_id' => $context['course_id'],
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'is_practice' => true,
                'delivery_mode' => 'offline',
                'eligibility_check_enabled' => false,
            ]);

            $paperService = app(ExamPaperService::class);
            $routinesCreated = 0;
            $questionsAttached = 0;

            foreach (self::DEMO_SUBJECTS as $subjectName => $subjectId) {
                $mcqIds = Question::where('subject_id', $subjectId)
                    ->where('status', 'approved')
                    ->where('question_type', 'mcq')
                    ->orderBy('sort_order')
                    ->limit(5)
                    ->pluck('id')
                    ->all();

                if (count($mcqIds) < 2) {
                    $mcqIds = array_merge($mcqIds, $this->seedMcqQuestions($subjectId, $context, $admin?->id, 5 - count($mcqIds)));
                }

                if (empty($mcqIds)) {
                    $this->command?->warn("Skipping {$subjectName}: no MCQ questions available.");

                    continue;
                }

                $routine = ExamRoutine::where('exam_id', $exam->id)
                    ->where('subject_id', $subjectId)
                    ->where('batch_id', $context['batch_id'])
                    ->first();

                if (!$routine) {
                    $routine = ExamRoutine::create([
                        'exam_id' => $exam->id,
                        'subject_id' => $subjectId,
                        'batch_id' => $context['batch_id'],
                        'exam_type_id' => $examType->id,
                        'course_id' => $context['course_id'],
                        'class_id' => $context['class_id'],
                        'exam_date' => now()->toDateString(),
                        'start_time' => '15:00:00',
                        'end_time' => '16:00:00',
                        'duration_minutes' => 45,
                        'total_marks' => count($mcqIds),
                        'pass_marks' => max(1, (int) floor(count($mcqIds) * 0.4)),
                        'mark_config' => [
                            'mcq' => ['enabled' => true, 'max_marks' => count($mcqIds), 'pass_marks' => 2, 'evaluation' => 'auto'],
                            'cq' => ['enabled' => false, 'max_marks' => 0, 'pass_marks' => 0, 'evaluation' => 'manual'],
                            'written' => ['enabled' => false, 'max_marks' => 0, 'pass_marks' => 0, 'evaluation' => 'manual'],
                            'practical' => ['enabled' => false, 'max_marks' => 0, 'pass_marks' => 0, 'evaluation' => 'manual'],
                        ],
                        'status' => 'draft',
                        'created_by' => $admin?->id,
                    ]);
                }

                $routine->update([
                    'exam_date' => now()->toDateString(),
                    'start_time' => '15:00:00',
                    'end_time' => '16:00:00',
                    'duration_minutes' => 45,
                    'total_marks' => count($mcqIds),
                    'status' => 'published',
                ]);

                $items = collect($mcqIds)->values()->map(fn ($qid, $i) => [
                    'question_id' => $qid,
                    'sort_order' => $i + 1,
                ])->all();

                $paperService->syncQuestions($routine, $items, false);
                $routinesCreated++;
                $questionsAttached += count($mcqIds);
            }

            $exam->update(['status' => 'published']);
            ExamRoutine::where('exam_id', $exam->id)->update(['status' => 'published']);

            $this->command?->info("Demo practice exam ready: \"{$exam->name}\" ({$exam->id})");
            $this->command?->info("Batch: {$context['batch_name']} — {$routinesCreated} routine(s), {$questionsAttached} question(s) attached.");
            $this->command?->info('Students in this batch can open Student Portal → Practice Center → Start Practice.');
        });
    }

    /**
     * @return array{batch_id: string, batch_name: string, course_id: string, class_id: string, session_id: string}|null
     */
    private function resolveContext(): ?array
    {
        $enrollment = Enrollment::with('batch.course')
            ->whereIn('status', ['active', 'pending'])
            ->whereNotNull('batch_id')
            ->first();

        if ($enrollment?->batch) {
            $batch = $enrollment->batch;

            return [
                'batch_id' => $batch->id,
                'batch_name' => $batch->name,
                'course_id' => $batch->course_id,
                'class_id' => $batch->course?->class_id ?? $enrollment->enrolled_class_id,
                'session_id' => $enrollment->academic_session_id ?? $batch->academic_session_id,
            ];
        }

        $batch = Batch::with('course')->where('status', 'active')->first();
        if (!$batch) {
            return null;
        }

        return [
            'batch_id' => $batch->id,
            'batch_name' => $batch->name,
            'course_id' => $batch->course_id,
            'class_id' => $batch->course?->class_id,
            'session_id' => $batch->academic_session_id,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function seedMcqQuestions(string $subjectId, array $context, ?string $adminId, int $count): array
    {
        $samples = [
            ['content' => 'বস্তুর ওজন মাপার একক কোনটি?', 'options' => ['মিটার', 'কিলোগ্রাম', 'সেকেন্ড', 'অ্যাম্পিয়ার'], 'correct' => 1],
            ['content' => 'পানির রাসায়নিক সংকেত কী?', 'options' => ['CO₂', 'H₂O', 'NaCl', 'O₂'], 'correct' => 1],
            ['content' => 'গতি = দূরত্ব ÷ ?', 'options' => ['সময়', 'ভর', 'ত্বরণ', 'বল'], 'correct' => 0],
            ['content' => 'মাধ্যাকর্ষণ ত্বরণের মান প্রায় কত?', 'options' => ['9.8 m/s²', '3.14 m/s²', '6.67 m/s²', '1.6 m/s²'], 'correct' => 0],
            ['content' => 'তাপের একক কোনটি?', 'options' => ['জুল', 'নিউটন', 'ওহম', 'পাস্কাল'], 'correct' => 0],
        ];

        $ids = [];
        for ($i = 0; $i < min($count, count($samples)); $i++) {
            $s = $samples[$i];
            $q = Question::create([
                'created_by' => $adminId,
                'class_id' => $context['class_id'],
                'subject_id' => $subjectId,
                'course_id' => $context['course_id'],
                'batch_id' => $context['batch_id'],
                'question_type' => 'mcq',
                'difficulty' => 'easy',
                'marks' => 1,
                'content' => $s['content'],
                'options' => $s['options'],
                'correct_answer' => ['index' => $s['correct'], 'value' => $s['options'][$s['correct']]],
                'status' => 'approved',
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);
            $ids[] = $q->id;
        }

        return $ids;
    }
}
