<?php

namespace Modules\Core\app\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Core\app\Interfaces\BaseRepositoryInterface;

abstract class BaseRepository implements BaseRepositoryInterface
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * BaseRepository constructor.
     */
    public function __construct()
    {
        $this->model = app($this->getModelClass());
    }

    /**
     * Get the model class name.
     */
    abstract protected function getModelClass(): string;

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->model->with($relations)->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int|string $id, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->find($id, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findByField(string $field, mixed $value, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->with($relations)->where($field, $value)->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllByField(string $field, mixed $value, array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->where($field, $value)->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(int|string $id, array $data): Model
    {
        $record = $this->findById($id);
        if (!$record) {
            throw new \RuntimeException("Record with ID {$id} not found.");
        }
        $record->update($data);
        return $record->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById(int|string $id): bool
    {
        $record = $this->findById($id);
        if (!$record) {
            return false;
        }
        return $record->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteByIds(array $ids): bool
    {
        return $this->model->whereIn('id', $ids)->delete() > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function searchAndFilter(?string $search = null, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply search if the model uses Searchable trait
        if ($search && method_exists($this->model, 'scopeSearch')) {
            $query->search($search);
        }

        // Apply filter if the model uses Filterable trait
        if (!empty($filters) && method_exists($this->model, 'scopeFilter')) {
            $query->filter($filters);
        }

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function allWithTrashed(array $columns = ['*']): Collection
    {
        if (!in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model))) {
            return $this->all($columns);
        }
        return $this->model->withTrashed()->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int|string $id): bool
    {
        if (!in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model))) {
            return false;
        }
        return $this->model->withTrashed()->find($id)?->restore() ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(int|string $id): bool
    {
        if (!in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->model))) {
            return $this->deleteById($id);
        }
        return $this->model->withTrashed()->find($id)?->forceDelete() ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function count(array $criteria = []): int
    {
        $query = $this->model->query();
        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }
        return $query->count();
    }

    /**
     * {@inheritdoc}
     */
    public function exists(array $criteria): bool
    {
        $query = $this->model->query();
        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }
        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function pluck(string $column, ?string $key = null): Collection
    {
        return $this->model->pluck($column, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        DB::beginTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        DB::commit();
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        DB::rollBack();
    }
}
