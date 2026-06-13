<?php

use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamResult;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Teacher\app\Models\Teacher;

$examId = 'fa043250-6bc4-4c87-b73b-675870a490df';
$exam = Exam::find($examId);
$repo = app(ExamRoutineRepository::class);
$assessment = app(ExamAssessmentService::class);

echo "=== Model Test publish flags ===\n";
echo "exam offline status: {$exam->result_status}\n";
echo "exam online status: {$exam->online_result_status}\n";
echo "isResultChannelPublished offline: " . ($exam->isResultChannelPublished('offline') ? 'yes' : 'no') . "\n";
echo "isResultChannelPublished online: " . ($exam->isResultChannelPublished('online') ? 'yes' : 'no') . "\n";

$hasOnlineResults = ExamResult::where('exam_id', $examId)->where('status', 'published')
    ->whereHas('routine', fn ($q) => $q->whereIn('delivery_mode', ['online', 'hybrid']))->exists();
echo "has published online results: " . ($hasOnlineResults ? 'yes' : 'no') . "\n";

echo "\n=== Teacher ef28289a (Sohel rana) ===\n";
$teacher = Teacher::find('ef28289a-fc95-4890-b0c0-a1586198aced');
if ($teacher) {
    $routines = $repo->getRoutinesByTeacherSubjects((string) $teacher->id);
    echo "routines: {$routines->count()}\n";
    foreach ($routines->groupBy('exam_id') as $eid => $rows) {
        $e = $rows->first()->exam;
        $batchIds = $rows->pluck('batch_id')->filter()->unique()->values()->all();
        echo "Exam: {$e->name}\n";
        echo "  offline_status={$e->result_status} online_status={$e->online_result_status}\n";

        foreach (['offline', 'online'] as $ch) {
            $examPub = $e->isResultChannelPublished($ch);
            $hasResults = ExamResult::where('exam_id', $eid)->where('status', 'published')
                ->whereHas('routine', function ($q) use ($ch, $batchIds) {
                    if ($ch === 'online') {
                        $q->whereIn('delivery_mode', ['online', 'hybrid']);
                    } else {
                        $q->where(function ($q2) {
                            $q2->whereNull('delivery_mode')->orWhereIn('delivery_mode', ['', 'offline']);
                        });
                    }
                    if (!empty($batchIds)) {
                        $q->whereIn('batch_id', $batchIds);
                    }
                })->exists();
            $merit = $assessment->computeMeritList((string) $eid, 'batch', (string) ($batchIds[0] ?? ''), 10, $ch);
            echo "  {$ch}: examPublished=" . ($examPub ? 'Y' : 'N') . " hasResults=" . ($hasResults ? 'Y' : 'N') . " meritStudents={$merit['total_students']}\n";
        }
    }
}

echo "\n=== sohel taaz zero routines - why? ===\n";
$t2 = Teacher::find('552e4133-4bac-4dd1-b92b-a56d0b3d27ed');
if ($t2) {
    echo "subjects: {$t2->subjects()->count()}\n";
    $subjectIds = $t2->subjects()->pluck('subjects.id')->toArray();
    echo "subject ids: " . implode(',', array_slice($subjectIds, 0, 5)) . "\n";
    $allRoutines = \Modules\Exam\app\Models\ExamRoutine::published()->whereIn('subject_id', $subjectIds)->count();
    echo "published routines matching subjects (no batch filter): {$allRoutines}\n";
    $routines = $repo->getRoutinesByTeacherSubjects((string) $t2->id);
    echo "after batch filter: {$routines->count()}\n";
}

echo "\n=== Report API would 403 online Model Test? ===\n";
echo "online isResultChannelPublished: " . ($exam->isResultChannelPublished('online') ? 'allow' : '403 BLOCKED') . "\n";
echo "But merit online has 9 students - data exists!\n";

echo "\nDone.\n";
