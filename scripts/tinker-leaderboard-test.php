<?php

use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Teacher\app\Models\Teacher;

$repo = app(ExamRoutineRepository::class);
$assessment = app(ExamAssessmentService::class);

echo "=== TEACHERS WITH ROUTINES ===\n";
$teachers = Teacher::query()->limit(10)->get(['id', 'first_name', 'last_name', 'user_id']);
foreach ($teachers as $t) {
    $routines = $repo->getRoutinesByTeacherSubjects((string) $t->id);
    $subjectCount = $t->subjects()->count();
    $name = trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? ''));
    echo "Teacher {$t->id}: {$name} | subjects={$subjectCount} | routines={$routines->count()}\n";
    if ($routines->count() > 0) {
        $examIds = $routines->pluck('exam_id')->unique()->values()->all();
        echo "  exam_ids: " . implode(', ', $examIds) . "\n";
    }
}

echo "\n=== LEADERBOARD CONTEXT SIMULATION ===\n";
$sampleTeacher = $teachers->first(fn ($t) => $repo->getRoutinesByTeacherSubjects((string) $t->id)->count() > 0);
if (!$sampleTeacher) {
    echo "No teacher with routines found.\n";
} else {
    $teacherId = (string) $sampleTeacher->id;
    echo "Using teacher {$teacherId}\n";
    $routines = $repo->getRoutinesByTeacherSubjects($teacherId);
    foreach ($routines->groupBy('exam_id') as $examId => $examRoutines) {
        $exam = $examRoutines->first()?->exam;
        if (!$exam) {
            continue;
        }
        $batchIds = $examRoutines->pluck('batch_id')->filter()->unique()->values()->all();
        $offlinePub = $exam->isResultChannelPublished('offline');
        $onlinePub = $exam->isResultChannelPublished('online');
        echo "Exam: {$exam->name} ({$examId})\n";
        echo "  exam status: offline={$exam->result_status} online={$exam->online_result_status}\n";
        echo "  isResultChannelPublished: offline=" . ($offlinePub ? 'yes' : 'no') . " online=" . ($onlinePub ? 'yes' : 'no') . "\n";
        echo "  teacher batches: " . implode(', ', $batchIds) . "\n";

        foreach (['offline', 'online'] as $channel) {
            $merit = $assessment->computeMeritList((string) $examId, null, null, 50, $channel);
            echo "  merit {$channel}: total_students={$merit['total_students']} merit_rows=" . count($merit['merit_list'] ?? []) . "\n";
            if (!empty($batchIds)) {
                $batchId = (string) $batchIds[0];
                $scoped = $assessment->computeMeritList((string) $examId, 'batch', $batchId, 50, $channel);
                echo "  merit {$channel} batch {$batchId}: total_students={$scoped['total_students']}\n";
            }
        }
    }
}

echo "\n=== EXAMS WITH PUBLISHED CHANNEL ===\n";
$publishedExams = Exam::query()
    ->where('is_practice', false)
    ->where(function ($q) {
        $q->where('result_status', 'published')
            ->orWhere('online_result_status', 'published');
    })
    ->limit(8)
    ->get(['id', 'name', 'result_status', 'online_result_status']);

foreach ($publishedExams as $exam) {
    echo "{$exam->name} | offline={$exam->result_status} | online={$exam->online_result_status}\n";
    foreach (['offline', 'online'] as $channel) {
        if (!$exam->isResultChannelPublished($channel)) {
            echo "  {$channel}: NOT published on exam record\n";
            continue;
        }
        $merit = $assessment->computeMeritList((string) $exam->id, null, null, 5, $channel);
        echo "  {$channel}: students={$merit['total_students']} toppers=" . count($merit['subject_toppers'] ?? []) . "\n";
    }
}

echo "\n=== TEACHERS WITHOUT SUBJECTS BUT WITH ROUTINE DUTY ===\n";
$dutyOnly = Teacher::query()
    ->whereDoesntHave('subjects')
    ->whereIn('id', function ($q) {
        $q->select('teacher_id')->from('exam_routines')->whereNotNull('teacher_id');
    })
    ->limit(5)
    ->get();
foreach ($dutyOnly as $t) {
    $count = $repo->getRoutinesByTeacherSubjects((string) $t->id)->count();
    echo "Teacher {$t->id}: routines after fix={$count}\n";
}

echo "\nDone.\n";
