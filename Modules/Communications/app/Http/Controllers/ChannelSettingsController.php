<?php

namespace Modules\Communications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Modules\Communications\Http\Requests\UpdateChannelSettingsRequest;
use Modules\Communications\Services\ChannelSettingsService;

class ChannelSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(protected ChannelSettingsService $service)
    {
    }

    public function show()
    {
        return $this->ok($this->service->getMasked());
    }

    public function update(UpdateChannelSettingsRequest $request)
    {
        return $this->ok($this->service->update($request->validated()), 'Channel settings saved.');
    }
}
