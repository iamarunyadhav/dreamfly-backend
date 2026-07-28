<?php

namespace Modules\Communications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Communications\Http\Requests\StoreMessageTemplateRequest;
use Modules\Communications\Http\Requests\UpdateMessageTemplateRequest;
use Modules\Communications\Http\Resources\MessageTemplateResource;
use Modules\Communications\Models\MessageTemplate;
use Modules\Communications\Services\MessageTemplateService;

class MessageTemplatesController extends Controller
{
    use ApiResponse;

    public function __construct(protected MessageTemplateService $service)
    {
    }

    public function index(Request $request)
    {
        $messageTemplates = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'channel']),
        );

        return $this->ok(MessageTemplateResource::collection($messageTemplates));
    }

    public function store(StoreMessageTemplateRequest $request)
    {
        $messageTemplate = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new MessageTemplateResource($messageTemplate));
    }

    public function show(MessageTemplate $template)
    {
        return $this->ok(new MessageTemplateResource($template));
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $template)
    {
        $template = $this->service->update($template, $request->validated());

        return $this->ok(new MessageTemplateResource($template), 'Message template updated successfully.');
    }

    public function destroy(MessageTemplate $template)
    {
        $this->service->delete($template);

        return $this->noContent();
    }
}
