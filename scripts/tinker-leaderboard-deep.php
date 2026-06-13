<?php

use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Services\ExamAssessmentService;

$examId = 'fa043250-6bc4-4c87-b73b-675870a490df'; // Model Test from prior run
$exam = Exam::find($examId);
$assessment = app(ExamAssessmentService::class);

echo "=== EXAM: {$exam->name} ===\n";
echo "delivery_mode={$exam->delivery_mode} offline_status={$exam->result_status} online_status={$exam->online_result_status}\n\n";

echo "=== ROUTINES ===\n";
$routines = ExamRoutine::where('exam_id', $examId)->with('subject', 'batch')->get();
foreach ($routines as $r) {
    $dm = $r->delivery_mode ?? 'null';
    $ch = method_exists($r, 'deliveryChannel') ? $r->deliveryChannel() : '?';
    echo "routine {$r->id} | {$r->subject?->name} | batch={$r->batch?->name} | delivery_mode={$dm} | channel={$ch} | status={$r->status}\n";
}

echo "\n=== PUBLISHED RESULTS BY ROUTINE CHANNEL ===\n";
$results = ExamResult::where('exam_id', $examId)->where('status', 'published')->with('routine.subject', 'student')->get();
echo "Total published results: {$results->count()}\n";
foreach ($results->groupBy(fn ($r) => $r->routine?->deliveryChannel() ?? 'unknown') as $ch => $rows) {
    echo "Channel {$ch}: {$rows->count()} results\n";
    foreach ($rows->take(3) as $row) {
        $student = trim(($row->student?->first_name ?? '') . ' ' . ($row->student?->last_name ?? ''));
        echo "  - {$student} | {$row->routine?->subject?->name} | marks={$row->marks_obtained} | routine_mode={$row->routine?->delivery_mode}\n";
    }
}

echo "\n=== MERIT LIST CHANNEL TEST ===\n";
foreach (['offline', 'online'] as $channel) {
    $merit = $assessment->computeMeritList($examId, null, null, 20, $channel);
    echo "{$channel}: total={$merit['total_students']} rows=" . count($merit['merit_list'] ?? []) . "\n";
    foreach (array_slice($merit['merit_list'] ?? [], 0, 3) as $row) {
        echo "  rank {$row['rank']}: {$row['student_name']} {$row['total_marks']}/{$row['total_possible']}\n";
    }
}

echo "\n=== MONTHLY EXAM (has offline merit) ===\n";
$monthly = Exam::where('name', 'like', '%Monthly%')->first();
if ($monthly) {
    echo "Exam {$monthly->id} offline={$monthly->result_status} online={$monthly->online_result_status}\n";
    $merit = $assessment->computeMeritList((string) $monthly->id, null, null, 5, 'offline');
    echo "offline merit students={$merit['total_students']}\n";
}

echo "\n=== TEACHER USER LOGIN CHECK (Sohel rana) ===\n";
$teacher = \Modules\Teacher\app\Models\Teacher::where('first_name', 'like', '%Sohel%')->orWhere('last_name', 'like', '%rana%')->first();
if ($teacher) {
    $repo = app(\Modules\Exam\app\Repositories\ExamRoutineRepository::class);
    $routines = $repo->getRoutinesByTeacherSubjects((string) $teacher->id);
    echo "Teacher {$teacher->id} ({$teacher->first_name} {$teacher->last_name}) routines={$routines->count()}\n";
    foreach ($routines->groupBy('exam_id') as $eid => $rows) {
        $e = $rows->first()->exam;
        $offline = $e?->isResultChannelPublished('offline');
        $online = $e?->isResultChannelPublished('online');
        $offMerit = $assessment->computeMeritList((string) $eid, null, null, 5, 'offline');
        $onMerit = $assessment->computeMeritList((string) $eid, null, null, 5, 'online');
        echo "  {$e?->name}: offline_pub=" . ($offline ? 'Y' : 'N') . "({$offMerit['total_students']}) online_pub=" . ($online ? 'Y' : 'N') . "({$onMerit['total_students']})\n";
    }
}

echo "\nDone.\n";
