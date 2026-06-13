<?php

use Illuminate\Support\Facades\Route;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamController;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamTypeController;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamRoutineController;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamResultController;
use Modules\Exam\app\Http\Controllers\Api\V1\TeacherExamRoutineController;
use Modules\Exam\app\Http\Controllers\Api\V1\StudentExamController;
use Modules\Exam\app\Http\Controllers\Api\V1\QuestionController;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamAttemptController;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamBatchPolicyController;
use Modules\Exam\app\Http\Controllers\Api\V1\ExamEligibilityController;

/*
|--------------------------------------------------------------------------
| Exam Module API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 and protected by api.auth middleware.
| Role-based access is enforced per route group.
|
*/

// ===== PUBLIC READ-ONLY ROUTES (All authenticated users) =====
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    // Exams - specific routes MUST come before wildcard {id} routes
    Route::get('exams', [ExamController::class, 'index']);
    Route::get('exams/name-suggestions', [ExamController::class, 'nameSuggestions']);
    Route::get('exams/by-batch/{batchId}', [ExamController::class, 'byBatch']);
    Route::get('exams/by-course/{courseId}', [ExamController::class, 'byCourse']);
    Route::get('exams/by-class/{classId}', [ExamController::class, 'byClass']);
    Route::get('exams/{id}', [ExamController::class, 'show']);
    Route::get('exams/{id}/results', [ExamController::class, 'results']);

    // Exam Types
    Route::get('exam-types', [ExamTypeController::class, 'index']);
    Route::get('exam-types/{id}', [ExamTypeController::class, 'show']);

    // Exam Routines - specific routes MUST come before wildcard {id} routes
    Route::get('exam-routines', [ExamRoutineController::class, 'index']);
    Route::get('exam-routines/by-exam/{examId}', [ExamRoutineController::class, 'getByExam']);
    Route::get('exam-routines/by-batch/{batchId}', [ExamRoutineController::class, 'getByBatch']);
    Route::get('exam-routines/by-course/{courseId}', [ExamRoutineController::class, 'getByCourse']);
    Route::get('exam-routines/by-class/{classId}', [ExamRoutineController::class, 'getByClass']);
    Route::get('exam-routines/grid/{examId}', [ExamRoutineController::class, 'getGrid']);
    Route::get('exam-routines/calendar/{examId}', [ExamRoutineController::class, 'calendar']);
    Route::get('exam-routines/{id}/questions', [ExamRoutineController::class, 'questions']);
    Route::get('exam-routines/{id}/paper/pdf', [ExamRoutineController::class, 'exportQuestionPaper']);
    Route::get('exam-routines/{id}', [ExamRoutineController::class, 'show']);

    // Exam Results — specific routes before {id}
    Route::get('exam-results/subjects-summary', [ExamResultController::class, 'subjectsSummary']);
    Route::get('exam-results', [ExamResultController::class, 'index']);
    Route::get('exam-results/{id}', [ExamResultController::class, 'show']);

});

// ===== ADMIN RESULT PUBLISH ROUTES (before {id} wildcard conflicts) =====
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    Route::get('exam-results/summary', [ExamResultController::class, 'summary']);
    Route::post('exam-results/publish-bulk', [ExamResultController::class, 'bulkPublish']);
    Route::post('exam-results/{id}/publish', [ExamResultController::class, 'publish']);
    Route::post('exam-results/{id}/unpublish', [ExamResultController::class, 'unpublish']);
    Route::get('exams/{id}/publish-preview', [ExamController::class, 'publishPreview']);
    Route::post('exams/{id}/publish-results', [ExamController::class, 'publishResults']);
    Route::get('exams/{examId}/students/{studentId}/marksheet', [ExamController::class, 'downloadMarksheet']);
    Route::get('exams/{id}/eligibility', [ExamEligibilityController::class, 'index']);
    Route::post('exams/{id}/eligibility/sync', [ExamEligibilityController::class, 'sync']);
    Route::post('exams/{id}/eligibility/override', [ExamEligibilityController::class, 'override']);
    Route::get('exams/{id}/channel-policies', [ExamBatchPolicyController::class, 'index']);
    Route::put('exams/{id}/channel-policies', [ExamBatchPolicyController::class, 'update']);
});

// ===== TEACHER-SPECIFIC ROUTES =====
Route::middleware(['api.auth', 'role:super-admin,admin,teacher'])->prefix('v1')->group(function () {
    // Teacher exam schedule
    Route::get('teacher/exam-schedule', [TeacherExamRoutineController::class, 'mySchedule']);
    Route::get('teacher/exam-today', [TeacherExamRoutineController::class, 'todayDuties']);
    Route::get('teacher/exam-upcoming', [TeacherExamRoutineController::class, 'upcomingDuties']);
    Route::post('teacher/exam-attendance/{routineId}', [TeacherExamRoutineController::class, 'markAttendance']);
    // Teacher exam routines (for subjects they teach)
    Route::get('teacher/exam-routines', [TeacherExamRoutineController::class, 'myExamRoutines']);
    Route::get('teacher/exam-leaderboard/context', [TeacherExamRoutineController::class, 'leaderboardContext']);
    // Teacher exam routines grid (structured grid data like admin getGrid)
    Route::get('teacher/exam-routines/grid', [TeacherExamRoutineController::class, 'grid']);
});

// ===== STUDENT-SPECIFIC ROUTES =====
Route::middleware(['api.auth', 'role:super-admin,admin,student'])->prefix('v1')->group(function () {
    // Student exam routines
    Route::get('student/exam-routines', [StudentExamController::class, 'routines']);
    Route::get('student/exam-upcoming', [StudentExamController::class, 'upcoming']);
    Route::get('student/exam-next', [StudentExamController::class, 'nextExam']);

    // Student admit card
    Route::get('student/exam-admit-card/{examId}', [StudentExamController::class, 'admitCard']);
    Route::get('student/exam-admit-card/{examId}/download', [StudentExamController::class, 'downloadAdmitCard']);
    Route::get('exams/{id}/eligibility/me', [ExamEligibilityController::class, 'me']);

    // Student reminders
    Route::post('student/exam-reminder', [StudentExamController::class, 'setReminder']);

    // Student results
    Route::get('student/exam-results', [StudentExamController::class, 'results']);
    Route::get('student/exam-results/{examId}/leaderboard', [StudentExamController::class, 'leaderboard']);
    Route::get('student/exam-results/{examId}/provisional-leaderboard', [StudentExamController::class, 'provisionalLeaderboard']);
    Route::get('student/exam-results/{examId}/marksheet', [StudentExamController::class, 'downloadMarksheet']);

    // Practice center + live online exams
    Route::get('student/practice-routines', [StudentExamController::class, 'practiceRoutines']);
    Route::get('student/exams/live', [StudentExamController::class, 'liveExams']);

    // Exam attempts (practice + online)
    Route::post('exam-attempts/start', [ExamAttemptController::class, 'start']);
    Route::put('exam-attempts/{id}', [ExamAttemptController::class, 'update']);
    Route::post('exam-attempts/{id}/submit', [ExamAttemptController::class, 'submit']);
    Route::get('exam-attempts/my', [ExamAttemptController::class, 'myAttempts']);
    Route::get('exam-attempts/{id}', [ExamAttemptController::class, 'show']);
});

// ===== QUESTION APPROVAL (admin only) =====
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {
    Route::post('questions/{id}/approve', [QuestionController::class, 'approve']);
    Route::post('questions/{id}/reject', [QuestionController::class, 'reject']);
    Route::post('questions/{id}/send-back', [QuestionController::class, 'sendBack']);
});

// ===== QUESTION BANK (admin + teacher) =====
Route::middleware(['api.auth', 'role:super-admin,admin,teacher'])->prefix('v1')->group(function () {
    Route::get('questions', [QuestionController::class, 'index']);
    Route::get('questions/{id}/review-logs', [QuestionController::class, 'reviewLogs']);
    Route::get('questions/{id}', [QuestionController::class, 'show']);
    Route::post('questions/bulk', [QuestionController::class, 'bulkStore']);
    Route::post('questions/bulk-submit', [QuestionController::class, 'bulkSubmit']);
    Route::post('questions', [QuestionController::class, 'store']);
    Route::put('questions/{id}', [QuestionController::class, 'update']);
    Route::delete('questions/{id}', [QuestionController::class, 'destroy']);
    Route::post('questions/{id}/submit', [QuestionController::class, 'submit']);
    // Exam CRUD
    Route::post('exams', [ExamController::class, 'store']);
    Route::put('exams/{id}', [ExamController::class, 'update']);
    Route::delete('exams/{id}', [ExamController::class, 'destroy']);
    Route::post('exams/{id}/publish', [ExamController::class, 'publish']);

    // Exam Routine CRUD
    Route::post('exam-routines', [ExamRoutineController::class, 'store']);
    Route::put('exam-routines/{id}', [ExamRoutineController::class, 'update']);
    Route::delete('exam-routines/{id}', [ExamRoutineController::class, 'destroy']);

    // Exam Routine bulk operations
    Route::post('exam-routines/bulk', [ExamRoutineController::class, 'bulkStore']);
    Route::post('exam-routines/generate', [ExamRoutineController::class, 'generate']);

    // Exam Routine status management
    Route::post('exam-routines/prune/{examId}', [ExamRoutineController::class, 'pruneSubjects']);
    Route::post('exam-routines/publish/{examId}', [ExamRoutineController::class, 'publish']);
    Route::post('exam-routines/complete/{examId}', [ExamRoutineController::class, 'complete']);
    Route::post('exam-routines/cancel/{examId}', [ExamRoutineController::class, 'cancel']);

    // Exam Routine conflict detection
    Route::get('exam-routines/conflicts/{examId}', [ExamRoutineController::class, 'conflicts']);

    // Exam Routine PDF export
    Route::get('exam-routines/export-pdf/{examId}', [ExamRoutineController::class, 'exportPdf']);
    Route::put('exam-routines/{id}/questions', [ExamRoutineController::class, 'syncQuestions']);

    // Exam Result CRUD
    Route::post('exam-results', [ExamResultController::class, 'store']);
    Route::post('exam-results/bulk', [ExamResultController::class, 'bulkStore']);
    Route::put('exam-results/{id}', [ExamResultController::class, 'update']);
    Route::delete('exam-results/{id}', [ExamResultController::class, 'destroy']);

    // Exam Type CRUD
    Route::post('exam-types', [ExamTypeController::class, 'store']);
    Route::put('exam-types/{id}', [ExamTypeController::class, 'update']);
    Route::delete('exam-types/{id}', [ExamTypeController::class, 'destroy']);
});
