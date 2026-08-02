<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Http\Requests\StoreClientRequest;
use Modules\Clients\Http\Requests\UpdateClientRequest;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\ClientService;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;

class ClientsController extends Controller
{
    use ApiResponse;

    public function __construct(protected ClientService $service)
    {
    }

    public function index(Request $request)
    {
        if ($request->query('archived') === 'only') {
            $query = Client::onlyTrashed()->with('profilePhoto');

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($country = $request->query('country')) {
                $query->where('country', $country);
            }

            return $this->ok(ClientResource::collection(
                $query->latest()->paginate((int) $request->integer('per_page', 15))->withQueryString()
            ));
        }

        $clients = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'status', 'country']),
        );

        $clients->getCollection()->load('profilePhoto');

        return $this->ok(ClientResource::collection($clients));
    }

    public function store(StoreClientRequest $request, FolderService $folderService)
    {
        $client = DB::transaction(function () use ($request, $folderService) {
            $client = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);
            $folderService->createClientFolderTree($client, $request->user()->id);

            return $client;
        });

        return $this->created(new ClientResource($client));
    }

    public function show(Client $client)
    {
        $client->load('profilePhoto');

        return $this->ok(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client = $this->service->update($client, $request->validated());

        return $this->ok(new ClientResource($client->load('profilePhoto')), 'Client updated successfully.');
    }

    public function uploadProfilePhoto(Request $request, Client $client, FileService $fileService, FolderService $folderService)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $file = DB::transaction(function () use ($request, $client, $fileService, $folderService) {
            $folder = $folderService->clientSubfolder($client, 'Profile Photo', $request->user()->id);
            $file = $fileService->uploadForClientFolder($request->file('file'), $client->id, $folder->id, $request->user()->id);

            $client->forceFill(['profile_photo_file_id' => $file->id])->save();

            return $file;
        });

        return $this->created(new FileResource($file), 'Profile photo updated.');
    }

    public function destroy(Request $request, Client $client, FolderService $folderService)
    {
        DB::transaction(function () use ($request, $client, $folderService) {
            $folderService->archiveDeletedClientFolderTree($client, $request->user()->id);
            $this->service->delete($client);
        });

        return $this->noContent();
    }

    public function restore(Request $request, int $clientId, FolderService $folderService)
    {
        $client = Client::withTrashed()->findOrFail($clientId);

        DB::transaction(function () use ($request, $client, $folderService) {
            $client->restore();
            $folderService->restoreClientFolderTree($client->refresh(), $request->user()->id);
        });

        return $this->ok(new ClientResource($client->refresh()), 'Client restored successfully.');
    }
}
