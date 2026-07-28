<?php

namespace Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Modules\System\Http\Requests\UpdateSystemSettingRequest;
use Modules\System\Http\Resources\SystemSettingResource;
use Modules\System\Services\SystemSettingService;

class SystemController extends Controller
{
    use ApiResponse;

    public function __construct(protected SystemSettingService $service)
    {
    }

    public function index()
    {
        return $this->ok($this->service->allAsMap());
    }

    public function update(UpdateSystemSettingRequest $request)
    {
        $setting = $this->service->upsert($request->validated('key'), $request->validated('value'));

        return $this->ok(new SystemSettingResource($setting), 'Setting updated successfully.');
    }
}
