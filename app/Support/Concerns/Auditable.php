<?php

namespace App\Support\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Standard audit-trail setup for every domain model: logs create/update/delete,
 * only dirty attributes, under a log name derived from the model's table.
 * Override getActivitylogOptions() on the model if different behaviour is needed.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logFillable()
            ->useLogName($this->getTable());
    }
}
