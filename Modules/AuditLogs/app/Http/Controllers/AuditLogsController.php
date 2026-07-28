<?php

namespace Modules\AuditLogs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\AuditLogs\Http\Resources\AuditLogResource;
use Modules\AuditLogs\Services\AuditLogService;

class AuditLogsController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuditLogService $service)
    {
    }

    public function index(Request $request)
    {
        $logs = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 25),
            with: ['causer'],
            filters: $request->only(['log_name', 'causer_id', 'subject_type', 'event', 'from', 'to']),
        );

        return $this->ok(AuditLogResource::collection($logs));
    }
}
