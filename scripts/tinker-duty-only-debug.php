<?php

use Modules\Exam\app\Models\ExamRoutine;
use Modules\Exam\app\Repositories\ExamRoutineRepository;
use Modules\Teacher\app\Models\Teacher;

$repo = app(ExamRoutineRepository::class);
$t = Teacher::find('9ebb1616-b606-4f75-b3c8-02a9e5644241');
if ($t) {
    $routines = ExamRoutine::where('teacher_id', $t->id)->with('exam')->get();
    foreach ($routines as $r) {
        echo "routine {$r->id} status={$r->status} exam={$r->exam?->name} is_practice=" . ($r->exam?->is_practice ? 'Y' : 'N') . "\n";
    }
    echo "getRoutinesByTeacherSubjects: " . $repo->getRoutinesByTeacherSubjects((string) $t->id)->count() . "\n";
}
