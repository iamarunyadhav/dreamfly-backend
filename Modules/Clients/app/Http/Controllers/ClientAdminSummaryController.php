<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Requests\UpsertClientAdminSummaryRequest;
use Modules\Clients\Http\Resources\ClientAdminSummaryResource;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientAdminSummary;
use Modules\Clients\Services\AdminSummaryDocumentService;
use Modules\Files\Http\Resources\FileResource;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;

class ClientAdminSummaryController extends Controller
{
    use ApiResponse;

    public function show(Client $client)
    {
        $summary = $client->adminSummary;

        return $this->ok($summary ? new ClientAdminSummaryResource($summary) : null);
    }

    public function saveDraft(UpsertClientAdminSummaryRequest $request, Client $client)
    {
        $summary = DB::transaction(function () use ($request, $client) {
            return ClientAdminSummary::updateOrCreate(
                ['client_id' => $client->id],
                [
                    ...$request->validated(),
                    'status' => 'draft',
                    'started_at' => $client->adminSummary?->started_at ?? now(),
                    'created_by' => $client->adminSummary?->created_by ?? $request->user()->id,
                    'updated_by' => $request->user()->id,
                ],
            );
        });

        return $this->ok(new ClientAdminSummaryResource($summary), 'Admin Summary draft saved.');
    }

    public function complete(Request $request, Client $client, CaseStepService $caseSteps)
    {
        $validated = $request->validate([
            'summary' => ['required', 'string', 'min:10'],
            'internal_notes' => ['nullable', 'string'],
            'client_share_notes' => ['nullable', 'string'],
            'form_data' => ['nullable', 'array'],
            'supervisor_id' => ['required', 'integer', 'exists:users,id'],
            'application_staff_id' => ['required', 'integer', 'exists:users,id'],
            'deadline_at' => ['required', 'date'],
        ]);

        if (! $request->user()?->can('clients.edit')) {
            abort(403);
        }

        if ($client->current_stage !== 'admin_summary') {
            throw ValidationException::withMessages([
                'current_stage' => ['Admin Summary can only be completed while the case is in Admin Summary stage.'],
            ]);
        }

        return DB::transaction(function () use ($request, $client, $validated, $caseSteps) {
            $summary = ClientAdminSummary::updateOrCreate(
                ['client_id' => $client->id],
                [
                    ...$validated,
                    'status' => 'completed',
                    'started_at' => $client->adminSummary?->started_at ?? now(),
                    'completed_at' => now(),
                    'completed_by' => $request->user()->id,
                    'created_by' => $client->adminSummary?->created_by ?? $request->user()->id,
                    'updated_by' => $request->user()->id,
                ],
            );

            $client->forceFill(['assigned_supervisor_id' => $validated['supervisor_id']])->save();

            // Stage advancement always goes through the case-step engine, never
            // a direct current_stage forceFill, so the Workflow tab's gating can
            // trust case_steps as the single source of truth.
            $step = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();
            if (! $step) {
                $step = $caseSteps->initializeForClient($client)->firstWhere('key', 'admin_summary');
            }
            $caseSteps->advance($step, $request->user()->id);

            return $this->ok([
                'admin_summary' => new ClientAdminSummaryResource($summary),
                'client' => new ClientResource($client->refresh()),
            ], 'Admin Summary completed and Application Unit assigned.');
        });
    }

    public function generateDocx(Request $request, Client $client, AdminSummaryDocumentService $documentService)
    {
        if (! $request->user()?->can('clients.edit')) {
            abort(403);
        }

        $summary = $client->adminSummary;
        if (! $summary) {
            throw ValidationException::withMessages([
                'admin_summary' => ['Save the Admin Summary before generating the document.'],
            ]);
        }

        $file = $documentService->generate($client, $summary, $request->user()->id);

        return $this->created([
            'admin_summary' => new ClientAdminSummaryResource($summary->refresh()),
            'file' => new FileResource($file),
        ], 'Admin Summary DOCX generated and saved.');
    }
}
