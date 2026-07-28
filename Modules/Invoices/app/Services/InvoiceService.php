<?php

namespace Modules\Invoices\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Repositories\Contracts\InvoiceRepositoryInterface;

class InvoiceService extends BaseService
{
    public function __construct(
        InvoiceRepositoryInterface $repository,
        private \Modules\Communications\Services\AlertDispatcher $alerts,
    ) {
        parent::__construct($repository);
    }

    public function create(array $attributes): Invoice
    {
        return DB::transaction(function () use ($attributes) {
            $items = $attributes['items'] ?? null;
            unset($attributes['items']);

            $attributes['reference_no'] ??= $this->nextReferenceNo();
            $attributes['issue_date'] ??= now()->toDateString();

            /** @var Invoice $invoice */
            $invoice = $this->repository->create($attributes);

            if (! empty($items)) {
                $invoice->items()->createMany($this->normalizeItems($items));
            }

            $invoice = $this->syncStatus($invoice)->load('items', 'payments', 'client');

            $this->alerts->trigger('invoice_generated', [
                'client_id' => $invoice->client_id,
                'client_reference' => $invoice->client?->reference_no,
                'client_name' => $invoice->client?->full_name,
                'invoice_id' => $invoice->id,
                'invoice_reference' => $invoice->reference_no,
                'amount' => number_format((int) $invoice->total_payable),
                'due_date' => optional($invoice->due_date)->format('d M Y'),
            ], "invoice-{$invoice->id}-generated");

            return $invoice;
        });
    }

    public function update(Model $model, array $attributes): Invoice
    {
        return DB::transaction(function () use ($model, $attributes) {
            $items = $attributes['items'] ?? null;
            unset($attributes['items']);

            /** @var Invoice $invoice */
            $invoice = $this->repository->update($model, $attributes);

            if ($items !== null) {
                $invoice->items()->delete();
                $invoice->items()->createMany($this->normalizeItems($items));
            }

            return $this->syncStatus($invoice)->load('items', 'payments');
        });
    }

    public function syncStatus(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, ['cancelled', 'waived', 'refunded'], true)) {
            return $invoice;
        }

        $invoice->loadMissing('items', 'payments');
        $paid = $invoice->paid_amount;
        $payable = $invoice->total_payable;
        $status = match (true) {
            $payable > 0 && $paid >= $payable => 'paid',
            $paid > 0 => 'partially_paid',
            $invoice->due_date && $invoice->due_date->isPast() && $invoice->status !== 'draft' => 'overdue',
            $invoice->status === 'sent' => 'issued',
            default => $invoice->status ?: 'draft',
        };

        if ($invoice->status !== $status) {
            $invoice->forceFill(['status' => $status])->save();
        }

        return $invoice->refresh();
    }

    /**
     * Flag issued/partially-paid invoices whose due date has passed and still
     * carry a balance as overdue. Terminal and fully-paid invoices are skipped.
     * Returns the number of invoices newly marked overdue.
     */
    public function markOverdue(): int
    {
        $candidates = Invoice::with(['items', 'payments'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereIn('status', ['issued', 'sent', 'partial', 'partially_paid'])
            ->get();

        $count = 0;
        foreach ($candidates as $invoice) {
            if ($invoice->balance <= 0) {
                continue;
            }

            $invoice->forceFill(['status' => 'overdue'])->save();
            $count++;

            $invoice->loadMissing('client');
            $this->alerts->trigger('invoice_overdue', [
                'client_id' => $invoice->client_id,
                'client_reference' => $invoice->client?->reference_no,
                'client_name' => $invoice->client?->full_name,
                'invoice_id' => $invoice->id,
                'invoice_reference' => $invoice->reference_no,
                'amount' => number_format((int) $invoice->balance),
                'due_date' => optional($invoice->due_date)->format('d M Y'),
            ], "invoice-{$invoice->id}-overdue");
        }

        return $count;
    }

    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(function (array $item) {
                $description = trim((string) ($item['description'] ?? $item['label'] ?? ''));
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = max(0, (int) ($item['unit_price'] ?? 0));
                $amount = array_key_exists('amount', $item) && $item['amount'] !== null
                    ? max(0, (int) $item['amount'])
                    : $quantity * $unitPrice;

                return [
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice ?: $amount,
                    'amount' => $amount,
                    'category' => $item['category'] ?? null,
                    'tax' => max(0, (int) ($item['tax'] ?? 0)),
                ];
            })
            ->filter(fn (array $item) => $item['description'] !== '')
            ->values()
            ->all();
    }

    /**
     * DF-INV-{sequence}-{year}. Counts trashed rows too and loops until free, so
     * a soft-deleted invoice's reference is never reused (unique-index collision).
     */
    private function nextReferenceNo(): string
    {
        $year = now()->year;
        $count = Invoice::withTrashed()->whereYear('created_at', $year)->count();

        do {
            $count++;
            $ref = sprintf('DF-INV-%d-%d', $count, $year);
        } while (Invoice::withTrashed()->where('reference_no', $ref)->exists());

        return $ref;
    }
}
