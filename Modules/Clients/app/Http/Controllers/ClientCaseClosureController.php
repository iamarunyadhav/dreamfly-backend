<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Requests\UpsertClientCaseClosureRequest;
use Modules\Clients\Http\Resources\ClientCaseClosureResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientCaseClosure;

class ClientCaseClosureController extends Controller
{
    use ApiResponse;

    public function show(Client $client)
    {
        $closure = $client->caseClosure;

        return $this->ok($closure ? new ClientCaseClosureResource($closure) : null);
    }

    public function saveDraft(UpsertClientCaseClosureRequest $request, Client $client)
    {
        $closure = $this->upsert($client, [
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ], $request->user()->id);

        return $this->ok(new ClientCaseClosureResource($closure), 'Case closure draft saved.');
    }

    /** Confirm the physical handover is done and the case file is archived. */
    public function archive(Request $request, Client $client)
    {
        $closure = $client->caseClosure;

        if (! $closure || ! $closure->all_documents_returned) {
            throw ValidationException::withMessages([
                'handover_checklist' => ['Mark every final document as returned before archiving the case.'],
            ]);
        }

        $closure->forceFill([
            'archived' => true,
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->ok(new ClientCaseClosureResource($closure->refresh()), 'Case file archived.');
    }

    /** Sign off the closure record - this is what the "closed" step's gate checks. */
    public function complete(Request $request, Client $client)
    {
        $closure = $client->caseClosure;

        if (! $closure || ! $closure->archived) {
            throw ValidationException::withMessages([
                'archived' => ['Archive the case file before completing closure.'],
            ]);
        }

        $closure->forceFill([
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->ok(new ClientCaseClosureResource($closure->refresh()), 'Case closure completed.');
    }

    private function upsert(Client $client, array $attributes, int $userId): ClientCaseClosure
    {
        return ClientCaseClosure::updateOrCreate(
            ['client_id' => $client->id],
            [
                ...$attributes,
                'created_by' => $client->caseClosure?->created_by ?? $userId,
            ],
        );
    }
}
