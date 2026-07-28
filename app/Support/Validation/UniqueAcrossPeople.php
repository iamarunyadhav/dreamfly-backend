<?php

namespace App\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Enforces phone/email/NIC/passport-no uniqueness across both `common_users`
 * and `clients` - the same person must not be registrable twice under two
 * different lead/client records. Soft-deleted rows are ignored (a deleted
 * lead's phone number is free to reuse).
 *
 * A lead and the client it was converted into legitimately share these values
 * by design, so the caller must pass the paired record's id to exclude via
 * `ignoreId` on that table's entry - see StoreCommonUserRequest/UpdateClientRequest
 * etc. for how the pairing is resolved.
 */
class UniqueAcrossPeople implements ValidationRule
{
    /**
     * @param  array<int, array{table: string, ignoreId?: int|null}>  $tables
     */
    public function __construct(
        private readonly string $column,
        private readonly array $tables,
        private readonly string $label,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '') {
            return;
        }

        foreach ($this->tables as $table) {
            $query = DB::table($table['table'])
                ->whereNull('deleted_at')
                ->where($this->column, $value);

            if (! empty($table['ignoreId'])) {
                $query->where('id', '!=', $table['ignoreId']);
            }

            if ($query->exists()) {
                $fail("This {$this->label} is already used by another record.");

                return;
            }
        }
    }
}
