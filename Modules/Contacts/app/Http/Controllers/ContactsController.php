<?php

namespace Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Contacts\Http\Requests\StoreContactRequest;
use Modules\Contacts\Http\Requests\UpdateContactRequest;
use Modules\Contacts\Http\Resources\ContactResource;
use Modules\Contacts\Models\Contact;
use Modules\Contacts\Services\ContactService;

class ContactsController extends Controller
{
    use ApiResponse;

    public function __construct(protected ContactService $service)
    {
    }

    public function index(Request $request)
    {
        $contacts = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'type']),
        );

        return $this->ok(ContactResource::collection($contacts));
    }

    public function store(StoreContactRequest $request)
    {
        $contact = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new ContactResource($contact));
    }

    public function show(Contact $contact)
    {
        return $this->ok(new ContactResource($contact));
    }

    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $contact = $this->service->update($contact, $request->validated());

        return $this->ok(new ContactResource($contact), 'Contact updated successfully.');
    }

    public function destroy(Contact $contact)
    {
        $this->service->delete($contact);

        return $this->noContent();
    }
}
