<?php

namespace Modules\Core\app\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*'], array $relations = []): Collection;

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator;

    /**
     * Find a record by its ID.
     */
    public function findById(int|string $id, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Find a record by a specific field.
     */
    public function findByField(string $field, mixed $value, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Find all records matching a condition.
     */
    public function findAllByField(string $field, mixed $value, array $columns = ['*'], array $relations = []): Collection;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update an existing record.
     */
    public function update(int|string $id, array $data): Model;

    /**
     * Delete a record.
     */
    public function deleteById(int|string $id): bool;

    /**
     * Delete multiple records.
     */
    public function deleteByIds(array $ids): bool;

    /**
     * Get records with search and filter.
     */
    public function searchAndFilter(?string $search = null, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get records with trashed (soft deletes).
     */
    public function allWithTrashed(array $columns = ['*']): Collection;

    /**
     * Restore soft-deleted records.
     */
    public function restore(int|string $id): bool;

    /**
     * Force delete a record.
     */
    public function forceDelete(int|string $id): bool;

    /**
     * Count records.
     */
    public function count(array $criteria = []): int;

    /**
     * Check if records exist.
     */
    public function exists(array $criteria): bool;

    /**
     * Pluck values from a column.
     */
    public function pluck(string $column, ?string $key = null): Collection;

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit a database transaction.
     */
    public function commit(): void;

    /**
     * Rollback a database transaction.
     */
    public function rollback(): void;
}
