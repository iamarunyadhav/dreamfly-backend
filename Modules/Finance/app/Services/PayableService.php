<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Models\Payable;

/**
 * Money the consultancy owes to someone else - the mirror of receivables.
 * Settling a payable posts a real, immutable expense entry to the ledger, the
 * same discipline PaymentService uses for income.
 */
class PayableService
{
    public function create(array $attributes): Payable
    {
        return Payable::create([...$attributes, 'status' => 'pending']);
    }

    public function update(Payable $payable, array $attributes): Payable
    {
        $this->assertPending($payable, 'edited');
        $payable->update($attributes);

        return $payable->refresh();
    }

    /**
     * Mark a payable settled and post the matching expense entry. Idempotent
     * against double-settling: a paid/cancelled payable cannot be paid again.
     */
    public function pay(Payable $payable, array $attributes, int $userId): Payable
    {
        $this->assertPending($payable, 'paid');

        return DB::transaction(function () use ($payable, $attributes, $userId) {
            $entry = LedgerEntry::create([
                'type' => 'expense',
                'category' => $payable->category,
                'amount' => $payable->amount,
                'payment_method' => $attributes['payment_method'] ?? 'other',
                'source' => 'payable',
                'description' => trim($payable->payee.($attributes['reference'] ?? '' ? ' - '.$attributes['reference'] : '')),
                'is_locked' => false,
                'entry_date' => $attributes['paid_at'] ?? now()->toDateString(),
                'recorded_by' => $userId,
            ]);

            $payable->forceFill([
                'status' => 'paid',
                'payment_method' => $attributes['payment_method'] ?? 'other',
                'reference' => $attributes['reference'] ?? null,
                'paid_at' => $attributes['paid_at'] ?? now()->toDateString(),
                'paid_by' => $userId,
                'ledger_entry_id' => $entry->id,
            ])->save();

            return $payable->refresh();
        });
    }

    public function cancel(Payable $payable, string $reason): Payable
    {
        $this->assertPending($payable, 'cancelled');

        $payable->forceFill([
            'status' => 'cancelled',
            'notes' => trim(($payable->notes ? $payable->notes."\n" : '').'Cancelled: '.$reason),
        ])->save();

        return $payable->refresh();
    }

    public function delete(Payable $payable): bool
    {
        $this->assertPending($payable, 'deleted');

        return (bool) $payable->delete();
    }

    private function assertPending(Payable $payable, string $action): void
    {
        if ($payable->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Only a pending payable can be {$action}. This one is already {$payable->status}."],
            ]);
        }
    }
}
