<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Finance\Models\DailyClosing;
use Modules\Finance\Models\LedgerEntry;
use Modules\System\Models\Notification;

class DailyClosingService
{
    /**
     * Compute the day's figures from the ledger. Returns the persisted closing
     * merged with fresh totals, or a computed (unsaved) preview if the day is
     * still open.
     *
     * @return array<string, mixed>
     */
    public function compute(string $date): array
    {
        $entries = LedgerEntry::whereDate('entry_date', $date);

        $incomeTotal = (int) (clone $entries)->where('type', 'income')->sum('amount');
        $expenseTotal = (int) (clone $entries)->where('type', 'expense')->sum('amount');
        $cashTotal = $this->methodNet($date, 'cash');
        $bankTotal = $this->methodNet($date, 'bank');
        $openingBalance = $this->openingBalance($date);
        $closingBalance = $openingBalance + $incomeTotal - $expenseTotal;

        $existing = DailyClosing::where('closing_date', $date)->first();

        return [
            'closing_date' => $date,
            'opening_balance' => $openingBalance,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'cash_total' => $cashTotal,
            'bank_total' => $bankTotal,
            'closing_balance' => $closingBalance,
            'status' => $existing?->status ?? 'open',
            'counted_cash' => $existing?->counted_cash,
            'variance' => $existing?->variance ?? 0,
            'closing' => $existing,
            'entry_count' => (clone $entries)->count(),
        ];
    }

    public function close(string $date, ?int $countedCash, ?string $notes, int $userId): DailyClosing
    {
        return DB::transaction(function () use ($date, $countedCash, $notes, $userId) {
            $existing = DailyClosing::where('closing_date', $date)->first();
            if ($existing && $existing->status === 'closed') {
                throw ValidationException::withMessages(['closing_date' => 'This day is already closed. Reopen it to make changes.']);
            }

            $figures = $this->compute($date);
            $variance = $countedCash !== null ? $countedCash - $figures['cash_total'] : 0;

            $closing = DailyClosing::updateOrCreate(
                ['closing_date' => $date],
                [
                    'opening_balance' => $figures['opening_balance'],
                    'income_total' => $figures['income_total'],
                    'expense_total' => $figures['expense_total'],
                    'cash_total' => $figures['cash_total'],
                    'bank_total' => $figures['bank_total'],
                    'closing_balance' => $figures['closing_balance'],
                    'counted_cash' => $countedCash,
                    'variance' => $variance,
                    'status' => 'closed',
                    'notes' => $notes,
                    'closed_by' => $userId,
                    'closed_at' => now(),
                    'created_by' => $existing->created_by ?? $userId,
                ]
            );

            // Lock the day's entries so the closed period is immutable.
            LedgerEntry::whereDate('entry_date', $date)->update([
                'is_locked' => true,
                'daily_closing_id' => $closing->id,
            ]);

            return $closing->refresh();
        });
    }

    public function reopen(DailyClosing $closing, string $reason, int $userId): DailyClosing
    {
        if ($closing->status !== 'closed') {
            throw ValidationException::withMessages(['status' => 'Only a closed day can be reopened.']);
        }

        return DB::transaction(function () use ($closing, $reason, $userId) {
            LedgerEntry::where('daily_closing_id', $closing->id)->update([
                'is_locked' => false,
                'daily_closing_id' => null,
            ]);

            $closing->forceFill([
                'status' => 'open',
                'reopened_by' => $userId,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ])->save();

            return $closing->refresh();
        });
    }

    /**
     * Notify the management tier that a day's closing is ready for review.
     * Only a closed day can be sent - an open day's figures are not final.
     */
    public function sendToAdmin(DailyClosing $closing, int $userId): DailyClosing
    {
        if ($closing->status !== 'closed') {
            throw ValidationException::withMessages(['status' => 'Only a closed day can be sent to admin.']);
        }

        foreach (['Super Admin', 'Admin'] as $role) {
            Notification::create([
                'role' => $role,
                'type' => 'daily_closing.sent',
                'title' => 'Daily closing ready for review',
                'body' => sprintf(
                    'Daily closing for %s — income %s, expense %s, closing balance %s.',
                    $closing->closing_date->format('d M Y'),
                    number_format($closing->income_total),
                    number_format($closing->expense_total),
                    number_format($closing->closing_balance),
                ),
                'metadata' => [
                    'daily_closing_id' => $closing->id,
                    'generated_file_id' => $closing->generated_file_id,
                ],
            ]);
        }

        $closing->forceFill([
            'sent_to_admin_at' => now(),
            'sent_to_admin_by' => $userId,
        ])->save();

        return $closing->refresh();
    }

    private function methodNet(string $date, string $method): int
    {
        $income = (int) LedgerEntry::whereDate('entry_date', $date)
            ->where('type', 'income')->where('payment_method', $method)->sum('amount');
        $expense = (int) LedgerEntry::whereDate('entry_date', $date)
            ->where('type', 'expense')->where('payment_method', $method)->sum('amount');

        return $income - $expense;
    }

    private function openingBalance(string $date): int
    {
        $previous = DailyClosing::where('closing_date', '<', $date)
            ->where('status', 'closed')
            ->orderByDesc('closing_date')
            ->first();

        return (int) ($previous?->closing_balance ?? 0);
    }
}
