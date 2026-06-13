<?php

use Illuminate\Support\Facades\Route;
use Modules\Cms\app\Http\Controllers\Api\V1\CmsController;
use Modules\Cms\app\Http\Controllers\Api\V1\CmsFoundationController;
use Modules\Cms\app\Http\Controllers\Api\V1\DownloadResourceController;
use Modules\Cms\app\Http\Controllers\Api\V1\GalleryController;
use Modules\Cms\app\Http\Controllers\Api\V1\PageController;
use Modules\Cms\app\Http\Controllers\Api\V1\PublicCmsController;
use Modules\Cms\app\Http\Controllers\Api\V1\StudyMaterialController;
use Modules\Cms\app\Http\Controllers\Api\V1\SuccessStoryController;
use Modules\Cms\app\Http\Controllers\Api\V1\TestimonialController;

// Public marketing site (no auth)
Route::prefix('v1/cms/public')->group(function () {
    Route::get('home', [PublicCmsController::class, 'home']);
    Route::get('sliders', [PublicCmsController::class, 'sliders']);
    Route::get('blog', [PublicCmsController::class, 'blog']);
    Route::get('blog/{slug}', [PublicCmsController::class, 'blogBySlug']);
    Route::get('pages/{slug}', [PublicCmsController::class, 'pageBySlug']);
    Route::get('testimonials', [PublicCmsController::class, 'testimonials']);
    Route::get('galleries', [PublicCmsController::class, 'galleries']);
    Route::get('success-stories', [PublicCmsController::class, 'successStories']);
    Route::get('success-stories/{slug}', [PublicCmsController::class, 'successStoryBySlug']);
    Route::get('downloads', [PublicCmsController::class, 'downloads']);
    Route::get('downloads/{id}/file', [PublicCmsController::class, 'downloadFile']);
    Route::get('events', [PublicCmsController::class, 'events']);
    Route::get('notices', [PublicCmsController::class, 'notices']);
    Route::get('teachers', [PublicCmsController::class, 'teachers']);
    Route::get('teachers/{id}', [PublicCmsController::class, 'teacher']);
    Route::get('site-settings', [PublicCmsController::class, 'siteSettings']);
    Route::post('contact', [PublicCmsController::class, 'contact'])->middleware('throttle:6,1');
    Route::post('track', [PublicCmsController::class, 'track']);
});

// Read-only routes — all authenticated users can view CMS content
Route::middleware(['api.auth'])->prefix('v1')->group(function () {
    // Pages & blog (read)
    Route::get('pages/published', [PageController::class, 'published']);
    Route::get('pages', [PageController::class, 'index']);
    Route::get('pages/detail/{id}', [PageController::class, 'show']);
    Route::get('pages/{slug}', [PageController::class, 'showBySlug']);

    Route::get('blog/published', [PageController::class, 'publishedBlogs']);
    Route::get('blog', [PageController::class, 'blogs']);
    Route::get('blog/{slug}', [PageController::class, 'showBlogBySlug']);

    Route::get('sliders', [CmsController::class, 'sliders']);
    Route::get('sliders/{id}', [CmsController::class, 'showSlider']);

    Route::get('events', [CmsController::class, 'events']);
    Route::get('events/{id}', [CmsController::class, 'showEvent']);

    Route::get('galleries/published', [GalleryController::class, 'published']);
    Route::get('galleries/{id}', [GalleryController::class, 'show']);

    Route::get('testimonials/published', [TestimonialController::class, 'published']);
    Route::get('testimonials/{id}', [TestimonialController::class, 'show']);

    Route::get('success-stories/published', [SuccessStoryController::class, 'published']);
    Route::get('success-stories/{slug}', [SuccessStoryController::class, 'showBySlug']);

    Route::get('study-materials/published', [StudyMaterialController::class, 'published']);
    Route::get('study-materials/{id}/download', [StudyMaterialController::class, 'download']);
    Route::get('study-materials/{id}', [StudyMaterialController::class, 'show']);

    Route::get('downloads/published', [DownloadResourceController::class, 'published']);
    Route::get('downloads/{id}/download', [DownloadResourceController::class, 'download']);
    Route::get('downloads/{id}', [DownloadResourceController::class, 'show']);

    Route::post('cms/analytics/track', [CmsFoundationController::class, 'trackEvent']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view gallery'])->prefix('v1')->group(function () {
    Route::get('galleries', [GalleryController::class, 'index']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view testimonials'])->prefix('v1')->group(function () {
    Route::get('testimonials', [TestimonialController::class, 'index']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view success stories'])->prefix('v1')->group(function () {
    Route::get('success-stories', [SuccessStoryController::class, 'index']);
    Route::get('success-stories/detail/{id}', [SuccessStoryController::class, 'show']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view study materials'])->prefix('v1')->group(function () {
    Route::get('study-materials', [StudyMaterialController::class, 'index']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view download center'])->prefix('v1')->group(function () {
    Route::get('downloads', [DownloadResourceController::class, 'index']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|approve cms content|approve notice board'])->prefix('v1')->group(function () {
    Route::get('cms/approval-queue', [CmsFoundationController::class, 'approvalQueue']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view cms audit logs'])->prefix('v1')->group(function () {
    Route::get('cms/audit-logs', [CmsFoundationController::class, 'auditLogs']);
    Route::get('cms/audit-logs/{entityType}/{entityId}', [CmsFoundationController::class, 'entityAuditLogs']);
    Route::get('pages/{id}/audit-logs', [PageController::class, 'auditLogs']);
    Route::get('testimonials/{id}/audit-logs', [TestimonialController::class, 'auditLogs']);
    Route::get('galleries/{id}/audit-logs', [GalleryController::class, 'auditLogs']);
    Route::get('success-stories/{id}/audit-logs', [SuccessStoryController::class, 'auditLogs']);
    Route::get('study-materials/{id}/audit-logs', [StudyMaterialController::class, 'auditLogs']);
    Route::get('downloads/{id}/audit-logs', [DownloadResourceController::class, 'auditLogs']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|view cms analytics|view cms audit logs'])->prefix('v1')->group(function () {
    Route::get('cms/analytics/summary', [CmsFoundationController::class, 'analyticsSummary']);
    Route::get('cms/analytics/dashboard', [CmsFoundationController::class, 'analyticsDashboard']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create cms pages|create sliders|create events|create gallery|create testimonials|create success stories|create study materials|create download center'])->prefix('v1')->group(function () {
    Route::post('cms/media/upload', [CmsFoundationController::class, 'uploadMedia']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create cms pages'])->prefix('v1')->group(function () {
    Route::post('pages', [PageController::class, 'store']);
    Route::post('blog', [PageController::class, 'storeBlog']);
    Route::post('pages/{id}/submit-for-review', [PageController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit cms pages'])->prefix('v1')->group(function () {
    Route::put('pages/{id}', [PageController::class, 'update']);
    Route::post('pages/{id}/publish', [PageController::class, 'publish']);
    Route::post('pages/{id}/unpublish', [PageController::class, 'unpublish']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete cms pages'])->prefix('v1')->group(function () {
    Route::delete('pages/{id}', [PageController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|approve cms content|approve notice board'])->prefix('v1')->group(function () {
    Route::post('pages/{id}/approve', [PageController::class, 'approve']);
    Route::post('pages/{id}/reject', [PageController::class, 'reject']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create sliders'])->prefix('v1')->group(function () {
    Route::post('sliders', [CmsController::class, 'storeSlider']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit sliders'])->prefix('v1')->group(function () {
    Route::put('sliders/{id}', [CmsController::class, 'updateSlider']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete sliders'])->prefix('v1')->group(function () {
    Route::delete('sliders/{id}', [CmsController::class, 'destroySlider']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create events'])->prefix('v1')->group(function () {
    Route::post('events', [CmsController::class, 'storeEvent']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit events'])->prefix('v1')->group(function () {
    Route::put('events/{id}', [CmsController::class, 'updateEvent']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete events'])->prefix('v1')->group(function () {
    Route::delete('events/{id}', [CmsController::class, 'destroyEvent']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create gallery'])->prefix('v1')->group(function () {
    Route::post('galleries', [GalleryController::class, 'store']);
    Route::post('galleries/{id}/submit-for-review', [GalleryController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit gallery'])->prefix('v1')->group(function () {
    Route::put('galleries/{id}', [GalleryController::class, 'update']);
    Route::post('galleries/{id}/activate', [GalleryController::class, 'activate']);
    Route::post('galleries/{id}/deactivate', [GalleryController::class, 'deactivate']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete gallery'])->prefix('v1')->group(function () {
    Route::delete('galleries/{id}', [GalleryController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|approve cms content'])->prefix('v1')->group(function () {
    Route::post('galleries/{id}/approve', [GalleryController::class, 'approve']);
    Route::post('galleries/{id}/reject', [GalleryController::class, 'reject']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create testimonials'])->prefix('v1')->group(function () {
    Route::post('testimonials', [TestimonialController::class, 'store']);
    Route::post('testimonials/{id}/submit-for-review', [TestimonialController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit testimonials'])->prefix('v1')->group(function () {
    Route::put('testimonials/{id}', [TestimonialController::class, 'update']);
    Route::post('testimonials/{id}/activate', [TestimonialController::class, 'activate']);
    Route::post('testimonials/{id}/deactivate', [TestimonialController::class, 'deactivate']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete testimonials'])->prefix('v1')->group(function () {
    Route::delete('testimonials/{id}', [TestimonialController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|approve cms content'])->prefix('v1')->group(function () {
    Route::post('testimonials/{id}/approve', [TestimonialController::class, 'approve']);
    Route::post('testimonials/{id}/reject', [TestimonialController::class, 'reject']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create success stories'])->prefix('v1')->group(function () {
    Route::post('success-stories', [SuccessStoryController::class, 'store']);
    Route::post('success-stories/{id}/submit-for-review', [SuccessStoryController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit success stories'])->prefix('v1')->group(function () {
    Route::put('success-stories/{id}', [SuccessStoryController::class, 'update']);
    Route::post('success-stories/{id}/activate', [SuccessStoryController::class, 'activate']);
    Route::post('success-stories/{id}/deactivate', [SuccessStoryController::class, 'deactivate']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete success stories'])->prefix('v1')->group(function () {
    Route::delete('success-stories/{id}', [SuccessStoryController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create study materials'])->prefix('v1')->group(function () {
    Route::post('study-materials', [StudyMaterialController::class, 'store']);
    Route::post('study-materials/{id}/submit-for-review', [StudyMaterialController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit study materials'])->prefix('v1')->group(function () {
    Route::put('study-materials/{id}', [StudyMaterialController::class, 'update']);
    Route::post('study-materials/{id}/activate', [StudyMaterialController::class, 'activate']);
    Route::post('study-materials/{id}/deactivate', [StudyMaterialController::class, 'deactivate']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete study materials'])->prefix('v1')->group(function () {
    Route::delete('study-materials/{id}', [StudyMaterialController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|create download center'])->prefix('v1')->group(function () {
    Route::post('downloads', [DownloadResourceController::class, 'store']);
    Route::post('downloads/{id}/submit-for-review', [DownloadResourceController::class, 'submitForReview']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|edit download center'])->prefix('v1')->group(function () {
    Route::put('downloads/{id}', [DownloadResourceController::class, 'update']);
    Route::post('downloads/{id}/activate', [DownloadResourceController::class, 'activate']);
    Route::post('downloads/{id}/deactivate', [DownloadResourceController::class, 'deactivate']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|delete download center'])->prefix('v1')->group(function () {
    Route::delete('downloads/{id}', [DownloadResourceController::class, 'destroy']);
});

Route::middleware(['api.auth', 'role_or_permission:super-admin|admin|approve cms content'])->prefix('v1')->group(function () {
    Route::post('success-stories/{id}/approve', [SuccessStoryController::class, 'approve']);
    Route::post('success-stories/{id}/reject', [SuccessStoryController::class, 'reject']);
    Route::post('study-materials/{id}/approve', [StudyMaterialController::class, 'approve']);
    Route::post('study-materials/{id}/reject', [StudyMaterialController::class, 'reject']);
    Route::post('downloads/{id}/approve', [DownloadResourceController::class, 'approve']);
    Route::post('downloads/{id}/reject', [DownloadResourceController::class, 'reject']);
});
