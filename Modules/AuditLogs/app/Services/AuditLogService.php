<?php

namespace Modules\AuditLogs\Services;

use App\Support\Service\BaseService;
use Modules\AuditLogs\Repositories\Contracts\AuditLogRepositoryInterface;

class AuditLogService extends BaseService
{
    public function __construct(AuditLogRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
