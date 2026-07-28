<?php

namespace App\Support\Service;

use App\Support\Repository\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseService
{
    public function __construct(protected BaseRepositoryInterface $repository)
    {
    }

    public function list(array $with = []): Collection
    {
        return $this->repository->all($with);
    }

    public function paginate(int $perPage = 15, array $with = [], array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $with, $filters);
    }

    public function find(int|string $id, array $with = []): Model
    {
        return $this->repository->findOrFail($id, $with);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(fn () => $this->repository->create($attributes));
    }

    public function update(Model $model, array $attributes): Model
    {
        return DB::transaction(fn () => $this->repository->update($model, $attributes));
    }

    public function delete(Model $model): bool
    {
        return DB::transaction(fn () => $this->repository->delete($model));
    }
}
