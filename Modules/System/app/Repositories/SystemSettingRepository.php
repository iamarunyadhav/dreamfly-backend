<?php

namespace Modules\System\Repositories;

use App\Support\Repository\BaseRepository;
use Modules\System\Models\SystemSetting;
use Modules\System\Repositories\Contracts\SystemSettingRepositoryInterface;

class SystemSettingRepository extends BaseRepository implements SystemSettingRepositoryInterface
{
    public function __construct(SystemSetting $model)
    {
        parent::__construct($model);
    }

    public function findByKey(string $key): ?SystemSetting
    {
        return $this->query()->where('key', $key)->first();
    }
}
