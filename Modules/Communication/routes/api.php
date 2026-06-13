<?php

use Illuminate\Support\Facades\Route;
use Modules\Communication\app\Http\Controllers\Api\V1\CommunicationController;
use Modules\Communication\app\Http\Controllers\Api\V1\NoticeBoardController;

Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    Route::get('notifications/mine', [CommunicationController::class, 'myNotifications']);
    Route::get('notifications/unread-count', [CommunicationController::class, 'unreadCount']);
    Route::put('notifications/read-all', [CommunicationController::class, 'markAllRead']);
    Route::put('notifications/recipients/{id}/read', [CommunicationController::class, 'markRead']);

    Route::get('notifications', [CommunicationController::class, 'notifications']);
    Route::get('notifications/{id}', [CommunicationController::class, 'showNotification']);

    // Notice board — read (portal + admin list)
    Route::get('notices/published', [NoticeBoardController::class, 'published']);
    Route::get('notices', [NoticeBoardController::class, 'index']);
    Route::get('notices/{id}', [NoticeBoardController::class, 'show']);
});

Route::middleware(['api.auth', 'role:super-admin,admin,teacher'])->prefix('v1')->group(function () {
    Route::post('notifications', [CommunicationController::class, 'storeNotification']);
    Route::post('notifications/{id}/send', [CommunicationController::class, 'sendNotification']);
});

// Notice write + workflow — role OR permission (preserves admin/teacher access)
Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|teacher|create notice board'])->prefix('v1')->group(function () {
    Route::post('notices', [NoticeBoardController::class, 'store']);
    Route::post('notices/attachments/upload', [NoticeBoardController::class, 'uploadAttachment']);
    Route::post('notices/{id}/submit-for-review', [NoticeBoardController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|teacher|edit notice board'])->prefix('v1')->group(function () {
    Route::put('notices/{id}', [NoticeBoardController::class, 'update']);
    Route::post('notices/{id}/publish', [NoticeBoardController::class, 'publish']);
    Route::post('notices/{id}/unpublish', [NoticeBoardController::class, 'unpublish']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete notice board'])->prefix('v1')->group(function () {
    Route::delete('notices/{id}', [NoticeBoardController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|approve notice board|approve cms content'])->prefix('v1')->group(function () {
    Route::post('notices/{id}/approve', [NoticeBoardController::class, 'approve']);
    Route::post('notices/{id}/reject', [NoticeBoardController::class, 'reject']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view cms audit logs'])->prefix('v1')->group(function () {
    Route::get('notices/{id}/audit-logs', [NoticeBoardController::class, 'auditLogs']);
});
