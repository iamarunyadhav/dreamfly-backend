<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;

class VisaDecisionController extends Controller
{
    use ApiResponse;

    private const OUTCOMES = ['approved', 'refused', 'withdrawn', 'pending'];

    private const APPEAL_STATUSES = ['none', 'considering', 'lodged', 'won', 'lost', 'withdrawn'];

    public function record(Request $request, Client $client)
    {
        $validated = $request->validate([
            'visa_outcome' => ['required', Rule::in(self::OUTCOMES)],
            'outcome_recorded_at' => ['nullable', 'date'],
            'refusal_reason' => ['nullable', 'string', 'max:5000'],
            'appeal_status' => ['nullable', Rule::in(self::APPEAL_STATUSES)],
            'appeal_due_at' => ['nullable', 'date'],
            'appeal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // A refusal is only useful to the office if the reason is captured -
        // it drives the appeal decision and the outcomes report.
        if ($validated['visa_outcome'] === 'refused' && trim((string) ($validated['refusal_reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'refusal_reason' => ['Record the refusal reason given by the authority.'],
            ]);
        }

        $client->fill([
            'visa_outcome' => $validated['visa_outcome'],
            'refusal_reason' => $validated['visa_outcome'] === 'refused' ? $validated['refusal_reason'] : null,
            'appeal_status' => $validated['appeal_status'] ?? ($validated['visa_outcome'] === 'refused' ? 'considering' : 'none'),
            'appeal_due_at' => $validated['appeal_due_at'] ?? null,
            'appeal_notes' => $validated['appeal_notes'] ?? null,
            'outcome_recorded_by' => $request->user()->id,
        ]);

        if (! empty($validated['outcome_recorded_at'])) {
            $client->outcome_recorded_at = $validated['outcome_recorded_at'];
        }

        $client->save();

        return $this->ok(
            new ClientResource($client->refresh()),
            'Visa decision recorded.',
        );
    }

    /** Upload the grant letter / refusal letter into the client's Final Documents folder. */
    public function uploadDecisionDocument(Request $request, Client $client, FileService $files, FolderService $folders)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $folder = $folders->clientSubfolder($client, 'Final Documents', $request->user()->id);
        $file = $files->uploadForClientFolder($request->file('file'), $client->id, $folder->id, $request->user()->id);

        $client->forceFill(['decision_file_id' => $file->id])->save();

        return $this->created([
            'client' => new ClientResource($client->refresh()),
            'file' => new FileResource($file),
        ], 'Decision document uploaded.');
    }
}
