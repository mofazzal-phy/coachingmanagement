<?php

namespace Modules\Cms\app\Services;

use Illuminate\Support\Collection;
use Modules\Cms\app\Enums\CmsApprovalStatus;
use Modules\Cms\app\Models\DownloadResource;
use Modules\Cms\app\Models\Gallery;
use Modules\Cms\app\Models\Page;
use Modules\Cms\app\Models\StudyMaterial;
use Modules\Cms\app\Models\SuccessStory;
use Modules\Cms\app\Models\Testimonial;
use Modules\Communication\app\Models\NoticeBoard;

class CmsApprovalQueueService
{
    public function getPendingItems(?string $entityType = null, ?string $search = null): Collection
    {
        $items = collect();

        $sources = $this->sources();

        if ($entityType) {
            $sources = array_filter($sources, fn ($source) => $source['entity_type'] === $entityType);
        }

        foreach ($sources as $source) {
            $query = $source['model']::query()
                ->with(['editor:id,name'])
                ->where('approval_status', CmsApprovalStatus::PendingReview->value);

            if (!empty($source['scope'])) {
                ($source['scope'])($query);
            }

            if ($search) {
                $query->search($search);
            }

            $records = $query
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get();

            foreach ($records as $record) {
                $items->push($this->mapItem($record, $source));
            }
        }

        return $items
            ->sortByDesc('submitted_at')
            ->values();
    }

    public function getPendingCount(): int
    {
        return $this->getPendingItems()->count();
    }

    public function getCountsByType(): array
    {
        return $this->getPendingItems()
            ->groupBy('entity_type')
            ->map(fn (Collection $group) => $group->count())
            ->all();
    }

    protected function sources(): array
    {
        return [
            [
                'entity_type' => 'pages',
                'entity_label' => 'Page',
                'admin_path' => '/dashboard/cms/pages',
                'api_group' => 'pages',
                'model' => Page::class,
                'title_column' => 'title',
                'scope' => fn ($q) => $q->where('content_type', 'page'),
            ],
            [
                'entity_type' => 'pages',
                'entity_label' => 'Blog Post',
                'content_subtype' => 'blog',
                'admin_path' => '/dashboard/cms/blog',
                'api_group' => 'blog',
                'model' => Page::class,
                'title_column' => 'title',
                'scope' => fn ($q) => $q->where('content_type', 'blog'),
            ],
            [
                'entity_type' => 'galleries',
                'entity_label' => 'Gallery Item',
                'admin_path' => '/dashboard/cms/gallery',
                'api_group' => 'galleries',
                'model' => Gallery::class,
                'title_column' => 'title',
            ],
            [
                'entity_type' => 'testimonials',
                'entity_label' => 'Testimonial',
                'admin_path' => '/dashboard/cms/testimonials',
                'api_group' => 'testimonials',
                'model' => Testimonial::class,
                'title_column' => 'name',
            ],
            [
                'entity_type' => 'success_stories',
                'entity_label' => 'Success Story',
                'admin_path' => '/dashboard/cms/success-stories',
                'api_group' => 'successStories',
                'model' => SuccessStory::class,
                'title_column' => 'title',
            ],
            [
                'entity_type' => 'study_materials',
                'entity_label' => 'Study Material',
                'admin_path' => '/dashboard/cms/study-materials',
                'api_group' => 'studyMaterials',
                'model' => StudyMaterial::class,
                'title_column' => 'title',
            ],
            [
                'entity_type' => 'download_resources',
                'entity_label' => 'Download Resource',
                'admin_path' => '/dashboard/cms/download-center',
                'api_group' => 'downloads',
                'model' => DownloadResource::class,
                'title_column' => 'title',
            ],
            [
                'entity_type' => 'notice_boards',
                'entity_label' => 'Notice',
                'admin_path' => '/dashboard/communication/notice-board',
                'api_group' => 'notices',
                'model' => NoticeBoard::class,
                'title_column' => 'title',
            ],
        ];
    }

    protected function mapItem($record, array $source): array
    {
        $titleColumn = $source['title_column'];

        return [
            'id' => $record->getKey(),
            'entity_type' => $source['entity_type'],
            'entity_label' => $source['entity_label'],
            'content_subtype' => $source['content_subtype'] ?? null,
            'title' => $record->{$titleColumn},
            'status' => $record->status ?? null,
            'approval_status' => $record->approval_status,
            'submitted_at' => $record->updated_at?->toIso8601String(),
            'editor' => $record->editor ? [
                'id' => $record->editor->id,
                'name' => $record->editor->name,
            ] : null,
            'admin_path' => $source['admin_path'],
            'api_group' => $source['api_group'],
        ];
    }
}
