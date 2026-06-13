<?php
namespace Modules\Core\app\Traits;

trait Searchable
{
    public function scopeSearch($query, ?string $search, array $columns = [])
    {
        if (empty($search)) return $query;
        $columns = !empty($columns) ? $columns : ($this->searchable ?? ['name']);
        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $col) $q->orWhere($col, 'LIKE', "%{$search}%");
        });
    }
}