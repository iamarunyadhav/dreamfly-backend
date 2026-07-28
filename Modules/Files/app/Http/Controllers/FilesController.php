<?php

namespace Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;
use Modules\Files\Http\Requests\StoreFileRequest;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Files\Services\DocumentRenderService;
use Modules\Files\Services\FileService;

class FilesController extends Controller
{
    use ApiResponse;

    public function __construct(protected FileService $service)
    {
    }

    public function index(Request $request)
    {
        $files = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 20),
            filters: $request->only(['folder_id', 'search', 'include_superseded']),
        );

        return $this->ok(FileResource::collection($files));
    }

    public function store(StoreFileRequest $request)
    {
        $file = $this->service->upload(
            uploadedFile: $request->file('file'),
            folderId: (int) $request->validated('folder_id'),
            uploadedBy: $request->user()->id,
        );

        return $this->created(new FileResource($file));
    }

    public function download(File $file)
    {
        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function signedDownload(File $file)
    {
        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The file's real bytes, served inline (not as an attachment) so a
     * browser can render it directly - PDFs and images in an <iframe>/<img>,
     * or as the source for a client-side docx/xlsx renderer.
     */
    public function raw(File $file)
    {
        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function signedRaw(File $file)
    {
        return $this->raw($file);
    }

    public function preview(File $file, DocumentRenderService $documents)
    {
        return response($documents->previewHtml($file), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function signedPreview(File $file, DocumentRenderService $documents)
    {
        return $this->preview($file, $documents);
    }

    public function generatePdf(Request $request, File $file, DocumentRenderService $documents)
    {
        $pdf = $documents->generatePdf($file, $request->user()->id);

        return $this->created(new FileResource($pdf), 'PDF preview generated and saved.');
    }

    public function share(Request $request, File $file, MessageService $messages)
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $message = $messages->send([
            ...$validated,
            'client_id' => $file->client_id,
            'body' => trim($validated['body'])."\n\nAttachment: ".\Illuminate\Support\Facades\URL::temporarySignedRoute(
                'api.files.signed-download',
                now()->addDays(7),
                $file->id
            ),
        ], $request->user()->id);

        return $this->created(new MessageResource($message), 'Document share recorded.');
    }

    public function verify(Request $request, File $file)
    {
        $file = $this->service->verify($file, $request->user()->id);

        return $this->ok(new FileResource($file), 'Document verified.');
    }

    /** Replace a document with a corrected copy, keeping the old one as history. */
    public function storeVersion(Request $request, File $file)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf,mp4,docx'],
            'version_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $replacement = $this->service->uploadNewVersion(
            current: $file,
            uploadedFile: $request->file('file'),
            uploadedBy: $request->user()->id,
            note: $request->input('version_note'),
        );

        return $this->created(new FileResource($replacement), 'New document version saved.');
    }

    public function versions(File $file)
    {
        return $this->ok(FileResource::collection($this->service->versionsOf($file)));
    }

    public function destroy(File $file)
    {
        $this->service->delete($file);

        return $this->noContent();
    }
}
