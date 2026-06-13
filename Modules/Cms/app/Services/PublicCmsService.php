<?php

namespace Modules\Cms\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Enums\CmsContentEventType;
use Modules\Cms\app\Models\Event;
use Modules\Cms\app\Models\Page;
use Modules\Cms\app\Models\Slider;
use Modules\Communication\app\Models\NoticeBoard;
use Modules\Communication\app\Services\NoticeBoardService;

class PublicCmsService
{
    public function __construct(
        protected PageService $pageService,
        protected TestimonialService $testimonialService,
        protected GalleryService $galleryService,
        protected SuccessStoryService $successStoryService,
        protected DownloadResourceService $downloadResourceService,
        protected CmsAnalyticsService $analyticsService,
        protected NoticeBoardService $noticeBoardService,
    ) {}

    public function home(): array
    {
        $limits = config('cms.public.home_limits', []);

        return [
            'sliders' => $this->listSliders($limits['sliders'] ?? 6),
            'blog' => $this->listBlogPosts($limits['blog'] ?? 6),
            'testimonials' => $this->listTestimonials($limits['testimonials'] ?? 8),
            'galleries' => $this->listGalleries($limits['galleries'] ?? 12, 'achievement'),
            'success_stories' => $this->listSuccessStories($limits['success_stories'] ?? 6),
            'events' => $this->listEvents($limits['events'] ?? 6),
            'downloads' => $this->listDownloads($limits['downloads'] ?? 8),
            'notices' => $this->listNotices($limits['notices'] ?? 5),
        ];
    }

    public function listSliders(?int $limit = null): array
    {
        $query = Slider::query()
            ->where('status', 'active')
            ->orderBy('sort_order');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->values()->all();
    }

    public function paginateBlog(int $perPage = 12): LengthAwarePaginator
    {
        $paginator = $this->pageService
            ->publishedQuery('blog')
            ->paginate($perPage);

        return $paginator->through(fn (Page $page) => $this->pageService->enrich($page));
    }

    public function listBlogPosts(?int $limit = null): array
    {
        $query = $this->pageService->publishedQuery('blog');

        if ($limit) {
            return $this->pageService->enrichCollection($query->limit($limit)->get());
        }

        return $this->pageService->enrichCollection($query->get());
    }

    public function getBlogBySlug(string $slug): ?array
    {
        $page = $this->pageService->findBySlug($slug, 'blog');

        if (!$this->isPublicPage($page)) {
            return null;
        }

        $page->recordView();

        return $this->pageService->enrich($page->fresh());
    }

    public function getPageBySlug(string $slug): ?array
    {
        $page = $this->pageService->findBySlug($slug, 'page');

        if (!$this->isPublicPage($page)) {
            return null;
        }

        $page->recordView();

        return $this->pageService->enrich($page->fresh());
    }

    public function listTestimonials(?int $limit = null): array
    {
        $query = $this->testimonialService->publishedQuery();

        if ($limit) {
            return $this->testimonialService->enrichCollection($query->limit($limit)->get());
        }

        return $this->testimonialService->enrichCollection($query->get());
    }

    public function listGalleries(?int $limit = null, ?string $category = null): array
    {
        $query = $this->galleryService->publishedQuery($category);

        if ($limit) {
            return $this->galleryService->enrichCollection($query->limit($limit)->get());
        }

        return $this->galleryService->enrichCollection($query->get());
    }

    public function listSuccessStories(?int $limit = null): array
    {
        $query = $this->successStoryService->publishedQuery();

        if ($limit) {
            return $this->successStoryService->enrichCollection($query->limit($limit)->get());
        }

        return $this->successStoryService->enrichCollection($query->get());
    }

    public function getSuccessStoryBySlug(string $slug): ?array
    {
        $story = $this->successStoryService->findBySlug($slug);

        if (!$this->isPublicActiveContent($story)) {
            return null;
        }

        $story->recordView();

        return $this->successStoryService->enrich($story->fresh());
    }

    public function listDownloads(?int $limit = null): array
    {
        $items = $this->downloadResourceService->listForUser(null);

        if ($limit) {
            $items = $items->take($limit);
        }

        return $this->downloadResourceService->enrichCollection($items);
    }

    public function downloadResource(string $id): ?array
    {
        $resource = $this->downloadResourceService->findById($id);

        if (!$resource || !$this->downloadResourceService->userCanAccess($resource, null)) {
            return null;
        }

        $this->downloadResourceService->recordDownload($resource);

        return $this->downloadResourceService->enrich($resource->fresh());
    }

    public function listEvents(?int $limit = null): array
    {
        $query = Event::query()
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('start_time');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->values()->all();
    }

    public function listNotices(?int $limit = null): array
    {
        $query = $this->publicNoticesQuery();

        if ($limit) {
            $notices = $query->limit($limit)->get();
        } else {
            $notices = $query->get();
        }

        return $this->noticeBoardService->enrichCollectionForPortal($notices);
    }

    public function trackEvent(string $contentType, string $contentId, string $eventType): array
    {
        $this->analyticsService->track(
            $contentType,
            $contentId,
            CmsContentEventType::from($eventType)
        );

        return $this->analyticsService->summary($contentType, $contentId);
    }

    protected function publicNoticesQuery(): Builder
    {
        return NoticeBoard::query()
            ->where('status', 'published')
            ->whereDate('publish_date', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('approval_status')
                    ->orWhere('approval_status', CmsApprovalStatus::Approved->value);
            })
            ->where(function (Builder $q) {
                $q->where('audience', 'all')->orWhereNull('audience');
            })
            ->orderByDesc('is_featured')
            ->orderBy('featured_order')
            ->orderByDesc('publish_date');
    }

    protected function isPublicPage(?Page $page): bool
    {
        if (!$page || $page->status !== 'published') {
            return false;
        }

        if ($page->approval_status && $page->approval_status !== CmsApprovalStatus::Approved->value) {
            return false;
        }

        return $page->isVisibleNow();
    }

    protected function isPublicActiveContent(?Model $model): bool
    {
        if (!$model || ($model->status ?? null) !== 'active') {
            return false;
        }

        if ($model->approval_status && $model->approval_status !== CmsApprovalStatus::Approved->value) {
            return false;
        }

        if (method_exists($model, 'isVisibleNow') && !$model->isVisibleNow()) {
            return false;
        }

        return true;
    }
}
