<?php

namespace Modules\System\Services;

use App\Support\Service\BaseService;
use Modules\System\Models\SystemSetting;
use Modules\System\Repositories\Contracts\SystemSettingRepositoryInterface;

class SystemSettingService extends BaseService
{
    public function __construct(SystemSettingRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function allAsMap(): array
    {
        return $this->repository->all()->pluck('value', 'key')->all();
    }

    public function upsert(string $key, ?string $value): SystemSetting
    {
        return SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
