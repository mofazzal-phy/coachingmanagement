<?php

use Illuminate\Support\Facades\Route;
use Modules\Hr\Http\Controllers\HrController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('hrs', HrController::class)->names('hr');
});
