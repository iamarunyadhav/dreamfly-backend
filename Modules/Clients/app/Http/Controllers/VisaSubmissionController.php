<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Clients\Http\Resources\VisaSubmissionResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\VisaSubmission;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;

class VisaSubmissionController extends Controller
{
    use ApiResponse;

    public function show(Client $client)
    {
        $submission = $client->visaSubmission;

        return $this->ok($submission ? new VisaSubmissionResource($submission->load('receiptFile')) : null);
    }

    public function save(Request $request, Client $client)
    {
        $validated = $request->validate([
            'submitted_at' => ['nullable', 'date'],
            'lodgement_reference' => ['nullable', 'string', 'max:255'],
            'tracking_reference' => ['nullable', 'string', 'max:255'],
            'submitted_to' => ['nullable', 'string', 'max:255'],
            'submission_method' => ['nullable', Rule::in(['vfs', 'embassy', 'online', 'courier', 'other'])],
            'appointment_at' => ['nullable', 'date'],
            'appointment_location' => ['nullable', 'string', 'max:255'],
            'biometrics_at' => ['nullable', 'date'],
            'expected_decision_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission = VisaSubmission::firstOrNew(['client_id' => $client->id]);

        if (! $submission->exists) {
            $submission->created_by = $request->user()->id;
        }

        $submission->fill([...$validated, 'updated_by' => $request->user()->id])->save();

        return $this->ok(
            new VisaSubmissionResource($submission->refresh()->load('receiptFile')),
            'Submission details saved.',
        );
    }

    /** Upload the lodgement receipt / acknowledgement slip into the client's Final Documents folder. */
    public function uploadReceipt(Request $request, Client $client, FileService $files, FolderService $folders)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $submission = VisaSubmission::firstOrNew(['client_id' => $client->id]);

        if (! $submission->exists) {
            $submission->created_by = $request->user()->id;
            $submission->save();
        }

        $folder = $folders->clientSubfolder($client, 'Final Documents', $request->user()->id);
        $file = $files->uploadForClientFolder($request->file('file'), $client->id, $folder->id, $request->user()->id);

        $submission->forceFill([
            'receipt_file_id' => $file->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->created([
            'submission' => new VisaSubmissionResource($submission->refresh()->load('receiptFile')),
            'file' => new FileResource($file),
        ], 'Submission receipt uploaded.');
    }
}
