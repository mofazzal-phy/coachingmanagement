<?php

namespace Modules\Cms\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Cms\app\Enums\CmsContentEventType;
use Modules\Cms\app\Models\CmsContentEvent;

class CmsAnalyticsService
{
    public function track(
        string $contentType,
        string $contentId,
        CmsContentEventType $eventType,
        ?string $userId = null
    ): void {
        try {
            DB::transaction(function () use ($contentType, $contentId, $eventType, $userId) {
                CmsContentEvent::create([
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                    'event_type' => $eventType->value,
                    'user_id' => $userId ?? auth()->id(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                $column = $eventType === CmsContentEventType::Download ? 'download_count' : 'view_count';

                if ($this->tableExists($contentType) && $this->columnExists($contentType, $column)) {
                    DB::table($contentType)
                        ->where('id', $contentId)
                        ->increment($column);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function summary(string $contentType, string $contentId): array
    {
        $views = CmsContentEvent::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('event_type', CmsContentEventType::View->value)
            ->count();

        $downloads = CmsContentEvent::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('event_type', CmsContentEventType::Download->value)
            ->count();

        return [
            'content_type' => $contentType,
            'content_id' => $contentId,
            'views' => $views,
            'downloads' => $downloads,
        ];
    }

    public function dashboard(?int $days = 30): array
    {
        $since = now()->subDays($days);

        $baseQuery = CmsContentEvent::query()->where('created_at', '>=', $since);

        $totalViews = (clone $baseQuery)
            ->where('event_type', CmsContentEventType::View->value)
            ->count();

        $totalDownloads = (clone $baseQuery)
            ->where('event_type', CmsContentEventType::Download->value)
            ->count();

        $byType = CmsContentEvent::query()
            ->select('content_type', 'event_type', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('content_type', 'event_type')
            ->get()
            ->groupBy('content_type')
            ->map(function ($rows, $type) {
                return [
                    'content_type' => $type,
                    'views' => (int) $rows->where('event_type', CmsContentEventType::View->value)->sum('total'),
                    'downloads' => (int) $rows->where('event_type', CmsContentEventType::Download->value)->sum('total'),
                ];
            })
            ->values()
            ->sortByDesc('views')
            ->values()
            ->all();

        return [
            'period_days' => $days,
            'total_views' => $totalViews,
            'total_downloads' => $totalDownloads,
            'by_content_type' => $byType,
            'top_views' => $this->topContent(CmsContentEventType::View, $since, 10),
            'top_downloads' => $this->topContent(CmsContentEventType::Download, $since, 10),
        ];
    }

    protected function topContent(CmsContentEventType $eventType, $since, int $limit = 10): array
    {
        return CmsContentEvent::query()
            ->select('content_type', 'content_id', DB::raw('count(*) as total'))
            ->where('event_type', $eventType->value)
            ->where('created_at', '>=', $since)
            ->groupBy('content_type', 'content_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'content_type' => $row->content_type,
                'content_id' => $row->content_id,
                'title' => $this->resolveTitle($row->content_type, $row->content_id),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    protected function resolveTitle(string $table, string $id): ?string
    {
        if (!$this->tableExists($table)) {
            return null;
        }

        $titleColumn = $table === 'testimonials' ? 'name' : 'title';

        if (!$this->columnExists($table, $titleColumn)) {
            return null;
        }

        return DB::table($table)->where('id', $id)->value($titleColumn);
    }

    protected function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    protected function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}
