<?php

use Illuminate\Support\Facades\Route;
use Modules\Teacher\app\Http\Controllers\Api\V1\TeacherController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('teachers', TeacherController::class)->names('teacher');
});
