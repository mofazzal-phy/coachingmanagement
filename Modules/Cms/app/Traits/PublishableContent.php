<?php

namespace Modules\Cms\app\Traits;

use Illuminate\Database\Eloquent\Builder;

trait PublishableContent
{
    public function initializePublishableContent(): void
    {
        $this->mergeFillable([
            'is_featured',
            'featured_order',
            'published_at',
            'scheduled_at',
            'expires_at',
            'seo_keywords',
            'og_image',
            'canonical_url',
            'view_count',
            'download_count',
            'updated_by',
        ]);

        $this->mergeCasts([
            'is_featured' => 'boolean',
            'featured_order' => 'integer',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'expires_at' => 'datetime',
            'view_count' => 'integer',
            'download_count' => 'integer',
        ]);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)->orderBy('featured_order');
    }

    public function scopePublishedNow(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeScheduledDue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '>', now());
            });
    }

    public function isVisibleNow(): bool
    {
        if ($this->published_at && $this->published_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
