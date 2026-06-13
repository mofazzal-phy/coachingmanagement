<?php

use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Teacher\app\Models\Teacher;

$assessment = app(ExamAssessmentService::class);
$repo = app(ExamRoutineRepository::class);
$examId = 'fa043250-6bc4-4c87-b73b-675870a490df';
$exam = Exam::find($examId);

echo "=== Fix 1: canViewChannelResults ===\n";
echo "offline: " . ($assessment->canViewChannelResults($exam, 'offline') ? 'yes' : 'no') . "\n";
echo "online: " . ($assessment->canViewChannelResults($exam, 'online') ? 'yes' : 'no') . "\n";
$meritOnline = $assessment->computeMeritList($examId, null, null, 10, 'online');
echo "online merit students: {$meritOnline['total_students']}\n";

echo "\n=== Fix 2: sohel taaz leaderboard routines (no batch filter) ===\n";
$t2 = Teacher::find('552e4133-4bac-4dd1-b92b-a56d0b3d27ed');
if ($t2) {
    $strict = $repo->getRoutinesByTeacherSubjects((string) $t2->id, true)->count();
    $relaxed = $repo->getRoutinesByTeacherSubjects((string) $t2->id, false)->count();
    echo "strict batch filter: {$strict}\n";
    echo "leaderboard (relaxed): {$relaxed}\n";
}

echo "\n=== Fix 3: batch scope merit (Sohel rana) ===\n";
$t1 = Teacher::find('ef28289a-fc95-4890-b0c0-a1586198aced');
if ($t1) {
    $routines = $repo->getRoutinesByTeacherSubjects((string) $t1->id, false);
    $batchId = (string) ($routines->where('exam_id', $examId)->pluck('batch_id')->filter()->first() ?? '');
    if ($batchId) {
        $scoped = $assessment->computeMeritList($examId, 'batch', $batchId, 10, 'online');
        echo "batch {$batchId} online students: {$scoped['total_students']}\n";
    }
}

echo "\n=== Fix 4: duty-only teachers ===\n";
$dutyOnly = Teacher::query()
    ->whereDoesntHave('subjects')
    ->whereIn('id', function ($q) {
        $q->select('teacher_id')->from('exam_routines')->whereNotNull('teacher_id');
    })
    ->limit(3)
    ->get();
foreach ($dutyOnly as $t) {
    $count = $repo->getRoutinesByTeacherSubjects((string) $t->id)->count();
    echo "Teacher {$t->id}: routines={$count}\n";
}

echo "\nDone.\n";
