<?php

namespace Modules\System\Repositories\Contracts;

use App\Support\Repository\BaseRepositoryInterface;
use Modules\System\Models\SystemSetting;

interface SystemSettingRepositoryInterface extends BaseRepositoryInterface
{
    public function findByKey(string $key): ?SystemSetting;
}
