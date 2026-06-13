<?php

use Illuminate\Support\Facades\Route;
use Modules\Academic\app\Http\Controllers\Api\V1\AcademicSessionController;
use Modules\Academic\app\Http\Controllers\Api\V1\ClassController;
use Modules\Academic\app\Http\Controllers\Api\V1\SectionController;
use Modules\Academic\app\Http\Controllers\Api\V1\SubjectController;
use Modules\Academic\app\Http\Controllers\Api\V1\AcademicGroupController;
use Modules\Academic\app\Http\Controllers\Api\V1\RoomController;
use Modules\Academic\app\Http\Controllers\Api\V1\ClassRoutineController;
use Modules\Academic\app\Http\Controllers\Api\V1\TeacherClassRoutineController;
use Modules\Academic\app\Http\Controllers\Api\V1\StudentClassRoutineController;

// Public read-only routes (accessible by teachers, students, guardians)
Route::middleware(['api.auth'])->prefix('v1')->group(function () {

    // Academic Sessions - teachers can view
    Route::get('academic-sessions/current', [AcademicSessionController::class, 'current']);
    Route::get('academic-sessions', [AcademicSessionController::class, 'index']);
    Route::get('academic-sessions/{id}', [AcademicSessionController::class, 'show']);

    // Classes - teachers can view
    Route::get('classes/list/all', [ClassController::class, 'listAll']);
    Route::get('classes', [ClassController::class, 'index']);
    Route::get('classes/{id}', [ClassController::class, 'show']);

    // Sections - teachers can view
    Route::get('sections/by-class/{classId}', [SectionController::class, 'byClass']);
    Route::get('sections', [SectionController::class, 'index']);
    Route::get('sections/{id}', [SectionController::class, 'show']);

    // Subjects - teachers can view
    Route::get('subjects/list/all', [SubjectController::class, 'listAll']);
    Route::get('subjects/by-class/{classId}', [SubjectController::class, 'byClass']);
    Route::get('subjects/by-course/{courseId}', [SubjectController::class, 'byCourse']);
    Route::get('subjects', [SubjectController::class, 'index']);
    Route::get('subjects/{id}', [SubjectController::class, 'show']);

    // Academic Groups - teachers can view
    Route::get('academic-groups/list/all', [AcademicGroupController::class, 'listAll']);
    Route::get('academic-groups/by-class/{classId}', [AcademicGroupController::class, 'byClass']);
    Route::get('academic-groups', [AcademicGroupController::class, 'index']);
    Route::get('academic-groups/{id}', [AcademicGroupController::class, 'show']);

    // Rooms - teachers can view
    Route::get('rooms/list/all', [RoomController::class, 'listAll']);
    Route::get('rooms', [RoomController::class, 'index']);
    Route::get('rooms/{id}', [RoomController::class, 'show']);

    // Class Routines - read-only for all authenticated users
    // NOTE: Specific routes MUST come before wildcard {id} routes
    Route::get('class-routines', [ClassRoutineController::class, 'index']);
    Route::get('class-routines/by-batch/{batchId}', [ClassRoutineController::class, 'getByBatch']);
    Route::get('class-routines/by-course/{courseId}', [ClassRoutineController::class, 'getByCourse']);
    Route::get('class-routines/by-class/{classId}', [ClassRoutineController::class, 'getByClass']);
    Route::get('class-routines/multi-batch/grid', [ClassRoutineController::class, 'multiBatchGrid']);
    Route::get('class-routines/multi-batch/stats', [ClassRoutineController::class, 'multiBatchStats']);
    Route::get('class-routines/multi-batch/conflicts', [ClassRoutineController::class, 'multiBatchConflicts']);
    Route::get('class-routines/teacher-load', [ClassRoutineController::class, 'teacherLoad']);
    Route::get('class-routines/room-utilization', [ClassRoutineController::class, 'roomUtilization']);
    Route::get('class-routines/{id}', [ClassRoutineController::class, 'show']);
});

// Admin-only write routes (super-admin, admin)
Route::middleware(['api.auth', 'role:super-admin,admin'])->prefix('v1')->group(function () {

    // Academic Sessions - CRUD
    Route::post('academic-sessions', [AcademicSessionController::class, 'store']);
    Route::put('academic-sessions/{id}', [AcademicSessionController::class, 'update']);
    Route::delete('academic-sessions/{id}', [AcademicSessionController::class, 'destroy']);

    // Classes - CRUD
    Route::post('classes', [ClassController::class, 'store']);
    Route::post('classes/{id}/assign-subjects', [ClassController::class, 'assignSubjects']);
    Route::put('classes/{id}', [ClassController::class, 'update']);
    Route::delete('classes/{id}', [ClassController::class, 'destroy']);

    // Sections - CRUD
    Route::post('sections', [SectionController::class, 'store']);
    Route::put('sections/{id}', [SectionController::class, 'update']);
    Route::delete('sections/{id}', [SectionController::class, 'destroy']);

    // Subjects - CRUD
    Route::post('subjects', [SubjectController::class, 'store']);
    Route::post('subjects/{id}/assign-groups', [SubjectController::class, 'assignGroups']);
    Route::put('subjects/{id}', [SubjectController::class, 'update']);
    Route::delete('subjects/{id}', [SubjectController::class, 'destroy']);

    // Academic Groups - CRUD
    Route::post('academic-groups', [AcademicGroupController::class, 'store']);
    Route::put('academic-groups/{id}', [AcademicGroupController::class, 'update']);
    Route::delete('academic-groups/{id}', [AcademicGroupController::class, 'destroy']);

    // Rooms - CRUD
    Route::post('rooms', [RoomController::class, 'store']);
    Route::put('rooms/{id}', [RoomController::class, 'update']);
    Route::delete('rooms/{id}', [RoomController::class, 'destroy']);

    // Class Routines - CRUD + Advanced
    Route::post('class-routines', [ClassRoutineController::class, 'store']);
    Route::post('class-routines/bulk', [ClassRoutineController::class, 'bulkStore']);
    Route::put('class-routines/{id}', [ClassRoutineController::class, 'update']);
    Route::delete('class-routines/{id}', [ClassRoutineController::class, 'destroy']);

    // Class Routines - Advanced Admin Endpoints
    Route::prefix('class-routines')->group(function () {
        Route::post('generate', [ClassRoutineController::class, 'generate']);
        Route::post('swap', [ClassRoutineController::class, 'swap']);
        Route::post('publish', [ClassRoutineController::class, 'publish']);
        Route::post('publish/multi-batch', [ClassRoutineController::class, 'publishMultiBatch']);
        Route::post('archive', [ClassRoutineController::class, 'archive']);
        Route::post('archive/multi-batch', [ClassRoutineController::class, 'archiveMultiBatch']);
        Route::get('conflicts', [ClassRoutineController::class, 'conflicts']);
        Route::post('lunch-break', [ClassRoutineController::class, 'setLunchBreak']);
        Route::post('off-day', [ClassRoutineController::class, 'setOffDay']);
    });
});

// Teacher-specific routes
Route::middleware(['api.auth', 'role:teacher'])->prefix('v1/teacher')->group(function () {
    Route::get('my-schedule', [TeacherClassRoutineController::class, 'mySchedule']);
    Route::get('my-schedule/today', [TeacherClassRoutineController::class, 'todayClasses']);
});

// Student-specific routes
Route::middleware(['api.auth', 'role:student'])->prefix('v1/student')->group(function () {
    Route::get('my-routine', [StudentClassRoutineController::class, 'myRoutine']);
    Route::get('my-routine/today', [StudentClassRoutineController::class, 'todayClasses']);
});
