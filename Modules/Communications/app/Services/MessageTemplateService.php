<?php

namespace Modules\Communications\Services;

use App\Support\Service\BaseService;
use Modules\Communications\Repositories\Contracts\MessageTemplateRepositoryInterface;

class MessageTemplateService extends BaseService
{
    public function __construct(MessageTemplateRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
