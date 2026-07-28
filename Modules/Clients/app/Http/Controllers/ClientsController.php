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
use Modules\Folders\Services\FolderService;

class ClientsController extends Controller
{
    use ApiResponse;

    public function __construct(protected ClientService $service)
    {
    }

    public function index(Request $request)
    {
        $clients = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'status', 'country']),
        );

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
        return $this->ok(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client = $this->service->update($client, $request->validated());

        return $this->ok(new ClientResource($client), 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $this->service->delete($client);

        return $this->noContent();
    }
}
