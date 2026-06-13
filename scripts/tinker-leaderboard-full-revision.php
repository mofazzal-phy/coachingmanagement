<?php

use Modules\Exam\app\Models\Exam;
use Modules\Exam\app\Models\ExamMeritSnapshot;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Exam\app\Services\ExamAssessmentService;
use Modules\Exam\app\Services\LeaderboardSettingsService;
use Modules\Teacher\app\Models\Teacher;

$assessment = app(ExamAssessmentService::class);
$settings = app(LeaderboardSettingsService::class);
$repo = app(ExamRoutineRepository::class);

echo "=== PHASE 3: Settings ===\n";
print_r($settings->get());

echo "\n=== PHASE 3: Merit snapshot table ===\n";
echo 'snapshots in DB: ' . ExamMeritSnapshot::count() . PHP_EOL;

$modelTestId = 'fa043250-6bc4-4c87-b73b-675870a490df';
$monthlyId = 'de1022d8-8c24-4fd8-ab3a-e00468fda7d6';

echo "\n=== REVISION: Channel access ===\n";
foreach ([$modelTestId => 'Model Test', $monthlyId => 'Monthly Exam'] as $id => $name) {
    $exam = Exam::find($id);
    if (!$exam) {
        continue;
    }
    echo "{$name}:\n";
    foreach (['offline', 'online'] as $ch) {
        $can = $assessment->canViewChannelResults($exam, $ch) ? 'Y' : 'N';
        $merit = $assessment->computeMeritList($id, null, null, 5, $ch);
        $snap = $merit['from_snapshot'] ?? false ? 'snapshot' : 'live';
        echo "  {$ch}: canView={$can} students={$merit['total_students']} source={$snap}\n";
    }
}

echo "\n=== REVISION: Teacher contexts ===\n";
$sohelTaaz = '552e4133-4bac-4dd1-b92b-a56d0b3d27ed';
$sohelRana = 'ef28289a-fc95-4890-b0c0-a1586198aced';
foreach ([$sohelTaaz => 'sohel taaz', $sohelRana => 'Sohel rana'] as $tid => $label) {
    $strict = $repo->getRoutinesByTeacherSubjects($tid, true)->count();
    $relaxed = $repo->getRoutinesByTeacherSubjects($tid, false)->count();
    echo "{$label}: strict={$strict} leaderboard={$relaxed}\n";
}

echo "\n=== REVISION: Batch scope with results ===\n";
$batchId = '2f7f45fa-743e-4374-8729-4b31a2c127e9';
$scoped = $assessment->computeMeritList($modelTestId, 'batch', $batchId, 10, 'online');
echo "Model Test online batch {$batchId}: students={$scoped['total_students']}\n";

echo "\n=== REVISION: Warm snapshot (Monthly offline) ===\n";
$assessment->warmMeritSnapshotsOnPublish($monthlyId, 'offline');
$after = $assessment->computeMeritList($monthlyId, null, null, 5, 'offline');
echo 'Monthly offline after warm: students=' . $after['total_students'] . ' from_snapshot=' . (($after['from_snapshot'] ?? false) ? 'Y' : 'N') . PHP_EOL;

echo "\n=== REVISION: Provisional MCQ guard ===\n";
$studentWithMcq = \Modules\Exam\app\Models\ExamResult::where('exam_id', $modelTestId)
    ->whereNotNull('exam_attempt_id')
    ->value('student_id');
if ($studentWithMcq) {
    try {
        $prov = $assessment->buildProvisionalMcqLeaderboard($modelTestId, (string) $studentWithMcq, 10);
        echo 'provisional built: students=' . $prov['total_students'] . PHP_EOL;
    } catch (\InvalidArgumentException $e) {
        echo 'provisional blocked (expected if official exists): ' . $e->getMessage() . PHP_EOL;
    }
}

echo "\nDone.\n";
