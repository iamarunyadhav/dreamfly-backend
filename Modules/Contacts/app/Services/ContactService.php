<?php

namespace Modules\Contacts\Services;

use App\Support\Service\BaseService;
use Modules\Contacts\Repositories\Contracts\ContactRepositoryInterface;

class ContactService extends BaseService
{
    public function __construct(ContactRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
