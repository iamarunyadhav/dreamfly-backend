<?php

namespace App\Support\Repository;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $with = []): Collection
    {
        return $this->query()->with($with)->get();
    }

    /**
     * Paginate with optional filters. Child repositories override
     * applyFilters() to translate request filters into query constraints.
     */
    public function paginate(int $perPage = 15, array $with = [], array $filters = []): LengthAwarePaginator
    {
        $query = $this->query()->with($with);

        $query = $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int|string $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    public function findOrFail(int|string $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    public function create(array $attributes): Model
    {
        $model = $this->model->newInstance()->forceFill($attributes);
        $model->save();

        // Refresh so DB-level column defaults (e.g. status columns) that weren't
        // set in-memory are reflected in the returned instance.
        return $model->refresh();
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->forceFill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query;
    }
}
