<?php

namespace Modules\System\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'key',
        'value',
    ];
}
