<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Clients\Http\Requests\StoreClientNoteRequest;
use Modules\Clients\Http\Resources\ClientNoteResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientNote;

class ClientNotesController extends Controller
{
    use ApiResponse;

    public function index(Client $client)
    {
        return $this->ok(ClientNoteResource::collection(
            $client->notes()->with('creator')->latest()->get()
        ));
    }

    public function store(StoreClientNoteRequest $request, Client $client)
    {
        $note = $client->notes()->create([
            ...$request->validated(),
            'visibility' => $request->validated('visibility', 'internal'),
            'created_by' => $request->user()->id,
        ]);

        return $this->created(new ClientNoteResource($note->load('creator')), 'Client note saved.');
    }

    public function destroy(Request $request, Client $client, ClientNote $note)
    {
        abort_unless($request->user()?->can('clients.edit'), 403);
        abort_unless($note->client_id === $client->id, 404);

        $note->delete();

        return $this->noContent();
    }
}
