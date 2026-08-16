<?php

namespace Modules\Folders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Files\Http\Resources\FileResource;
use Modules\Folders\Http\Requests\StoreFolderRequest;
use Modules\Folders\Http\Requests\UpdateFolderRequest;
use Modules\Folders\Http\Resources\FolderResource;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderCompressionService;
use Modules\Folders\Services\FolderService;

class FoldersController extends Controller
{
    use ApiResponse;

    public function __construct(protected FolderService $service)
    {
    }

    public function index()
    {
        return $this->ok($this->service->tree());
    }

    public function store(StoreFolderRequest $request)
    {
        $folder = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new FolderResource($folder));
    }

    public function show(Folder $folder)
    {
        return $this->ok(new FolderResource($folder->loadCount(['files', 'children'])));
    }

    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        $folder = $this->service->update($folder, $request->validated());

        return $this->ok(new FolderResource($folder), 'Folder updated successfully.');
    }

    public function propagate(Folder $folder)
    {
        abort_unless($folder->is_general, 422, 'Only general folder templates can be propagated.');

        $count = $this->service->propagateTemplateToExisting($folder);

        return $this->ok(['clients_updated' => $count], 'General folder propagated successfully.');
    }

    public function compress(Request $request, Folder $folder, FolderCompressionService $compression)
    {
        $file = $compression->compress($folder, $request->user()->id);

        return $this->created(new FileResource($file), 'Folder compressed and saved as a zip.');
    }

    public function destroy(Folder $folder)
    {
        $this->service->delete($folder);

        return $this->noContent();
    }
}
