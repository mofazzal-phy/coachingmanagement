<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core API Routes (Protected)
|--------------------------------------------------------------------------
|
| Core মডিউলের নিজস্ব কোনো CRUD রিসোর্স দরকার নেই।
| শুধু প্রয়োজনীয় ইউটিলিটি এন্ডপয়েন্ট (যেমন ফাইল আপলোড টেস্ট) রাখতে পারো।
| সব প্রটেক্টেড রুট api.auth (JWT) মিডলওয়্যার দিয়ে ঘেরা থাকবে।
|
*/

Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    // Example: File upload test (পরবর্তীতে লাগবে)
    // Route::post('upload', [FileController::class, 'upload']);
});
