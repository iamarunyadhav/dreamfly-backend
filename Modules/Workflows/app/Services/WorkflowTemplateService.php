<?php

namespace Modules\Workflows\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Workflows\Repositories\Contracts\WorkflowTemplateRepositoryInterface;

class WorkflowTemplateService extends BaseService
{
    public function __construct(WorkflowTemplateRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): Model
    {
        return DB::transaction(function () use ($attributes) {
            $steps = $attributes['steps'] ?? null;
            unset($attributes['steps']);

            $template = $this->repository->create($attributes);

            if (! empty($steps)) {
                $template->steps()->createMany($steps);
            }

            return $template->refresh();
        });
    }

    public function update(Model $model, array $attributes): Model
    {
        return DB::transaction(function () use ($model, $attributes) {
            $steps = $attributes['steps'] ?? null;
            unset($attributes['steps']);

            $template = $this->repository->update($model, $attributes);

            if ($steps !== null) {
                $template->steps()->delete();
                $template->steps()->createMany($steps);
            }

            return $template->refresh();
        });
    }
}
