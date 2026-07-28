<?php

namespace Modules\Folders\Repositories\Contracts;

use App\Support\Repository\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface FolderRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Root folders with their descendant tree eager loaded (recursive).
     */
    public function tree(): Collection;
}
