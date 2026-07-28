<?php

namespace Modules\Checklists\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Checklists\Models\ChecklistTemplateVersion;
use Modules\Checklists\Repositories\Contracts\ChecklistTemplateRepositoryInterface;

class ChecklistTemplateService extends BaseService
{
    public function __construct(ChecklistTemplateRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): Model
    {
        // New items start as drafts until explicitly published.
        $attributes['status'] ??= 'draft';
        $attributes['version'] ??= 1;

        return parent::create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        // Any content edit sends a published item back to draft; it must be
        // re-published to create a new version.
        if (! array_key_exists('status', $attributes) && $model->status === 'published') {
            $attributes['status'] = 'draft';
        }

        return parent::update($model, $attributes);
    }

    /**
     * Snapshot the current state as a new immutable version and mark the
     * template published. The version number increments per publish.
     */
    public function publish(ChecklistTemplate $template, int $userId): ChecklistTemplate
    {
        return DB::transaction(function () use ($template, $userId) {
            $nextVersion = ((int) $template->versions()->max('version')) + 1;

            ChecklistTemplateVersion::create([
                'checklist_template_id' => $template->id,
                'version' => $nextVersion,
                'title' => $template->title,
                'owner' => $template->owner,
                'category' => $template->category,
                'description' => $template->description,
                'is_required' => $template->is_required,
                'document_required' => $template->document_required,
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            $template->forceFill(['status' => 'published', 'version' => $nextVersion])->save();

            return $template->refresh();
        });
    }

    /**
     * Restore a template's editable fields from a past version (as a new draft).
     */
    public function restore(ChecklistTemplate $template, int $version): ChecklistTemplate
    {
        $snapshot = $template->versions()->where('version', $version)->first();

        if (! $snapshot) {
            throw ValidationException::withMessages(['version' => 'That version does not exist for this checklist item.']);
        }

        $template->forceFill([
            'title' => $snapshot->title,
            'owner' => $snapshot->owner,
            'category' => $snapshot->category,
            'description' => $snapshot->description,
            'is_required' => $snapshot->is_required,
            'document_required' => $snapshot->document_required,
            'status' => 'draft',
        ])->save();

        return $template->refresh();
    }
}
