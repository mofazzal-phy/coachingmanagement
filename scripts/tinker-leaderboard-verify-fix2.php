<?php

use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Teacher\app\Models\Teacher;

$examId = 'fa043250-6bc4-4c87-b73b-675870a490df';
$assessment = app(ExamAssessmentService::class);

echo "=== Online result routine batch_ids ===\n";
$results = ExamResult::where('exam_id', $examId)->where('status', 'published')
    ->whereHas('routine', fn ($q) => $q->whereIn('delivery_mode', ['online', 'hybrid']))
    ->with('routine')
    ->get();
foreach ($results->groupBy(fn ($r) => $r->routine?->batch_id ?? 'null') as $batchId => $rows) {
    echo "batch={$batchId} count=" . $rows->count() . "\n";
}

$batchId = (string) ($results->first()?->routine?->batch_id ?? '');
if ($batchId) {
    echo "\nMerit batch scope using result batch {$batchId}:\n";
    $scoped = $assessment->computeMeritList($examId, 'batch', $batchId, 10, 'online');
    echo "students: {$scoped['total_students']}\n";
}

echo "\n=== Duty-only teacher routine status ===\n";
$dutyOnly = Teacher::query()
    ->whereDoesntHave('subjects')
    ->whereIn('id', function ($q) {
        $q->select('teacher_id')->from('exam_routines')->whereNotNull('teacher_id');
    })
    ->limit(3)
    ->get();
foreach ($dutyOnly as $t) {
    $all = ExamRoutine::where('teacher_id', $t->id)->count();
    $published = ExamRoutine::published()->where('teacher_id', $t->id)->count();
    echo "Teacher {$t->id}: all={$all} published={$published}\n";
}

echo "\nDone.\n";
