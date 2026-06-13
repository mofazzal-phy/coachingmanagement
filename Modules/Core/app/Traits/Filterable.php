<?php
namespace Modules\Core\app\Traits;

trait Filterable
{
    public function scopeFilter($query, array $filters = [])
    {
        foreach ($filters as $col => $val) {
            if (!empty($val) && in_array($col, $this->filterable ?? [])) {
                is_array($val) ? $query->whereIn($col, $val) : $query->where($col, $val);
            }
        }
        return $query;
    }
}