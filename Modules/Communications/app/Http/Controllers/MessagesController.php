<?php

namespace Modules\Communications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Communications\Http\Requests\StoreMessageRequest;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;

class MessagesController extends Controller
{
    use ApiResponse;

    public function __construct(protected MessageService $service)
    {
    }

    public function index(Request $request)
    {
        $messages = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['channel', 'status', 'message_template_id']),
        );

        return $this->ok(MessageResource::collection($messages));
    }

    public function store(StoreMessageRequest $request)
    {
        $message = $this->service->send($request->validated(), $request->user()->id);

        return $this->created(new MessageResource($message));
    }
}
