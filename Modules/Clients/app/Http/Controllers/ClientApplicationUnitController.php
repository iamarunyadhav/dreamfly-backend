<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Requests\UpsertClientApplicationUnitRequest;
use Modules\Clients\Http\Resources\ClientApplicationUnitResource;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientApplicationUnit;
use Modules\Clients\Services\ApplicationChecklistRuntimeService;
use Modules\Clients\Services\ApplicationUnitDocumentService;
use Modules\Checklists\Http\Resources\CaseChecklistItemResource;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Files\Services\FileService;
use Modules\Folders\Models\Folder;
use Illuminate\Support\Str;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;

class ClientApplicationUnitController extends Controller
{
    use ApiResponse;

    public function show(Client $client)
    {
        return $this->ok($client->applicationUnit ? new ClientApplicationUnitResource($client->applicationUnit) : null);
    }

    public function saveDraft(UpsertClientApplicationUnitRequest $request, Client $client, ApplicationChecklistRuntimeService $runtime)
    {
        $applicationUnit = ClientApplicationUnit::updateOrCreate(
            ['client_id' => $client->id],
            [
                ...$request->validated(),
                'status' => 'draft',
                'started_at' => $client->applicationUnit?->started_at ?? now(),
                'created_by' => $client->applicationUnit?->created_by ?? $request->user()->id,
                'updated_by' => $request->user()->id,
            ],
        );

        $runtime->sync($applicationUnit);

        return $this->ok(new ClientApplicationUnitResource($applicationUnit), 'Application Unit draft saved.');
    }

    public function complete(UpsertClientApplicationUnitRequest $request, Client $client, ApplicationChecklistRuntimeService $runtime, CaseStepService $caseSteps)
    {
        if ($client->current_stage !== 'application_unit') {
            throw ValidationException::withMessages([
                'current_stage' => ['Application Unit can only be completed while the case is in Application Unit stage.'],
            ]);
        }

        $validated = $request->validated();
        if (empty($validated['form_data']['full_name_as_per_passport']) || empty($validated['form_data']['passport_number'])) {
            throw ValidationException::withMessages([
                'form_data' => ['Full name and passport number are required before completing Application Unit.'],
            ]);
        }

        return DB::transaction(function () use ($request, $client, $validated, $runtime, $caseSteps) {
            $applicationUnit = ClientApplicationUnit::updateOrCreate(
                ['client_id' => $client->id],
                [
                    ...$validated,
                    'status' => 'completed',
                    'started_at' => $client->applicationUnit?->started_at ?? now(),
                    'completed_at' => now(),
                    'completed_by' => $request->user()->id,
                    'created_by' => $client->applicationUnit?->created_by ?? $request->user()->id,
                    'updated_by' => $request->user()->id,
                ],
            );

            $runtime->sync($applicationUnit);

            // Stage advancement always goes through the case-step engine, never
            // a direct current_stage forceFill, so the Workflow tab's gating can
            // trust case_steps as the single source of truth.
            $step = CaseStep::where('client_id', $client->id)->where('key', 'application_unit')->first();
            if (! $step) {
                $step = $caseSteps->initializeForClient($client)->firstWhere('key', 'application_unit');
            }
            $caseSteps->advance($step, $request->user()->id);

            return $this->ok([
                'application_unit' => new ClientApplicationUnitResource($applicationUnit),
                'client' => new ClientResource($client->refresh()),
            ], 'Application Unit completed and Documentation Unit assigned.');
        });
    }

    public function generateDocx(Request $request, Client $client, ApplicationUnitDocumentService $documentService)
    {
        if (! ($request->user()?->can('application-unit.generate') || $request->user()?->can('clients.edit'))) {
            abort(403);
        }

        $applicationUnit = $client->applicationUnit;
        if (! $applicationUnit) {
            throw ValidationException::withMessages([
                'application_unit' => ['Save the Application Unit form before generating the document.'],
            ]);
        }

        $file = $documentService->generate($client, $applicationUnit, $request->user()->id);

        return $this->created([
            'application_unit' => new ClientApplicationUnitResource($applicationUnit->refresh()),
            'file' => new FileResource($file),
        ], 'Application data DOCX generated and saved.');
    }

    public function checklistItems(Client $client)
    {
        return $this->ok(CaseChecklistItemResource::collection(
            CaseChecklistItem::with('linkedFile')
                ->where('client_id', $client->id)
                ->orderBy('owner')
                ->orderBy('source_index')
                ->get()
        ));
    }

    public function uploadChecklistFile(Request $request, Client $client, FileService $files, ApplicationChecklistRuntimeService $runtime)
    {
        if (! ($request->user()?->can('application-unit.update') || $request->user()?->can('files.create'))) {
            abort(403);
        }

        $validated = $request->validate([
            'kind' => ['required', 'in:applicant,inviter,internal'],
            'index' => ['required', 'integer', 'min:0'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf,mp4,docx'],
        ]);

        $applicationUnit = $client->applicationUnit;
        if (! $applicationUnit) {
            throw ValidationException::withMessages([
                'application_unit' => ['Save the Application Unit checklist before linking documents.'],
            ]);
        }

        $column = ApplicationChecklistRuntimeService::columnFor($validated['kind']);
        $items = $applicationUnit->{$column} ?? [];
        if (! array_key_exists($validated['index'], $items)) {
            throw ValidationException::withMessages([
                'index' => ['Checklist item not found.'],
            ]);
        }

        return DB::transaction(function () use ($request, $client, $files, $applicationUnit, $column, $items, $validated, $runtime) {
            $existingFile = ($existingId = $items[$validated['index']]['linked_file_id'] ?? null)
                ? File::find($existingId)
                : null;

            // Re-uploading against a row that already has a document supersedes
            // it as a new version rather than orphaning the original.
            if ($existingFile) {
                $file = $files->uploadNewVersion(
                    current: $existingFile,
                    uploadedFile: $request->file('file'),
                    uploadedBy: $request->user()->id,
                    note: 'Replaced from the '.$validated['kind'].' checklist.',
                );
            } else {
                $folder = $this->checklistFolder($client, $validated['kind'], $request->user()->id);
                $file = $files->uploadForClientFolder(
                    uploadedFile: $request->file('file'),
                    clientId: $client->id,
                    folderId: $folder->id,
                    uploadedBy: $request->user()->id,
                );
            }

            $items[$validated['index']] = [
                ...$items[$validated['index']],
                // A freshly uploaded document is always pending re-verification,
                // even when the row it replaces had already been verified.
                'status' => 'pending',
                'linked_file_id' => $file->id,
                'linked_file_name' => $file->original_name,
                'linked_file_url' => route('api.files.download', $file->id),
                'linked_file_verified' => (bool) $file->verified,
                'file_version' => (int) $file->version,
                'uploaded_at' => now()->toISOString(),
            ];

            $applicationUnit->forceFill([
                $column => array_values($items),
                'updated_by' => $request->user()->id,
            ])->save();
            $runtime->sync($applicationUnit);

            return $this->created([
                'application_unit' => new ClientApplicationUnitResource($applicationUnit->refresh()),
                'file' => new FileResource($file),
            ], 'Checklist document uploaded and linked.');
        });
    }

    public function verifyChecklistItem(Request $request, Client $client, CaseChecklistItem $item, FileService $files, ApplicationChecklistRuntimeService $runtime)
    {
        if ($item->client_id !== $client->id) {
            abort(404);
        }
        if (! $item->linkedFile) {
            throw ValidationException::withMessages([
                'linked_file_id' => ['Upload or link a document before verification.'],
            ]);
        }

        return DB::transaction(function () use ($request, $item, $files, $runtime) {
            $files->verify($item->linkedFile, $request->user()->id);
            $item->forceFill([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'completed_at' => now(),
            ])->save();

            $runtime->syncJsonRow($item->refresh()->load('linkedFile'));

            return $this->ok(new CaseChecklistItemResource($item), 'Checklist document verified.');
        });
    }

    public function rejectChecklistItem(Request $request, Client $client, CaseChecklistItem $item, ApplicationChecklistRuntimeService $runtime)
    {
        if ($item->client_id !== $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        return DB::transaction(function () use ($request, $item, $runtime, $validated) {
            $item->forceFill([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $validated['reason'],
                'verified_at' => null,
                'verified_by' => null,
                'completed_at' => null,
            ])->save();

            $runtime->syncJsonRow($item->refresh()->load('linkedFile'));

            return $this->ok(new CaseChecklistItemResource($item), 'Checklist document rejected.');
        });
    }

    private function checklistFolder(Client $client, string $kind, int $userId): Folder
    {
        $root = Folder::firstOrCreate(
            ['name' => 'Clients', 'parent_id' => null],
            ['slug' => 'clients', 'is_active' => true, 'created_by' => $userId],
        );

        $clientFolder = Folder::where('parent_id', $root->id)
            ->where('name', 'like', $client->reference_no.'%')
            ->first();

        if (! $clientFolder) {
            $clientFolderName = trim($client->reference_no.' - '.$client->full_name);
            $clientFolder = Folder::create([
                'name' => $clientFolderName,
                'slug' => Str::slug($clientFolderName) ?: 'client-'.$client->id,
                'parent_id' => $root->id,
                'is_active' => true,
                'created_by' => $userId,
            ]);
        }

        $folderName = match ($kind) {
            'inviter' => 'Inviter Documents',
            'internal' => 'Application Unit',
            default => 'Applicant Documents',
        };

        return Folder::firstOrCreate(
            ['name' => $folderName, 'parent_id' => $clientFolder->id],
            [
                'slug' => Str::slug($clientFolder->name.' '.$folderName),
                'is_active' => true,
                'created_by' => $userId,
            ],
        );
    }
}
