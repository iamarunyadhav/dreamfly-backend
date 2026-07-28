<?php

namespace Modules\Forms\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Forms\Repositories\Contracts\FormRepositoryInterface;

class FormService extends BaseService
{
    public function __construct(FormRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $fields = $attributes['fields'] ?? null;
            unset($attributes['fields']);

            $form = $this->repository->create($attributes);

            if (! empty($fields)) {
                $form->fields()->createMany($fields);
            }

            return $form->refresh();
        });
    }

    public function update(Model $model, array $attributes): Model
    {
        return DB::transaction(function () use ($model, $attributes) {
            $fields = $attributes['fields'] ?? null;
            unset($attributes['fields']);

            $model = $this->repository->update($model, $attributes);

            if ($fields !== null) {
                $model->fields()->delete();
                $model->fields()->createMany($fields);
            }

            return $model->refresh();
        });
    }
}
