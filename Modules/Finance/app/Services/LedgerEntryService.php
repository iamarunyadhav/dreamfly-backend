<?php

namespace Modules\Finance\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Repositories\Contracts\LedgerEntryRepositoryInterface;

class LedgerEntryService extends BaseService
{
    public function __construct(LedgerEntryRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function update(Model $model, array $attributes): Model
    {
        /** @var LedgerEntry $model */
        $this->assertEditable($model);

        return parent::update($model, $attributes);
    }

    public function delete(Model $model): bool
    {
        /** @var LedgerEntry $model */
        $this->assertEditable($model);

        return parent::delete($model);
    }

    /**
     * Record an immutable adjustment against an existing entry (or a standalone
     * correction). A reason is mandatory - the original entry is never mutated.
     */
    public function adjust(array $attributes, int $userId): LedgerEntry
    {
        $original = ! empty($attributes['adjusts_entry_id'])
            ? LedgerEntry::find($attributes['adjusts_entry_id'])
            : null;

        return LedgerEntry::create([
            'type' => $attributes['type'] ?? $original?->type ?? 'expense',
            'category' => $attributes['category'] ?? $original?->category ?? 'adjustment',
            'amount' => (int) $attributes['amount'],
            'payment_method' => $attributes['payment_method'] ?? $original?->payment_method,
            'source' => 'adjustment',
            'adjusts_entry_id' => $original?->id,
            'description' => $attributes['description'] ?? ($original ? "Adjustment for entry #{$original->id}" : 'Adjustment'),
            'reason' => $attributes['reason'],
            'is_locked' => false,
            'entry_date' => $attributes['entry_date'] ?? now()->toDateString(),
            'recorded_by' => $userId,
        ]);
    }

    private function assertEditable(LedgerEntry $entry): void
    {
        if (! $entry->isEditable()) {
            throw ValidationException::withMessages([
                'entry' => 'This entry is locked or system-posted and cannot be edited or deleted. Record an adjustment instead.',
            ]);
        }
    }
}
