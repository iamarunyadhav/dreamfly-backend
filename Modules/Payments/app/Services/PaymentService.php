<?php

namespace Modules\Payments\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Models\Client;
use Modules\Files\Services\FileService;
use Modules\Finance\Models\LedgerEntry;
use Modules\Folders\Models\Folder;
use Modules\Invoices\Models\Invoice;
use Modules\Payments\Models\Payment;
use Modules\Payments\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentService extends BaseService
{
    /** Invoice statuses the operator set deliberately - never auto-overwritten. */
    private const TERMINAL_INVOICE_STATUSES = ['cancelled', 'waived', 'refunded'];

    public function __construct(
        PaymentRepositoryInterface $repository,
        private \Modules\Communications\Services\AlertDispatcher $alerts,
    ) {
        parent::__construct($repository);
    }

    public function create(array $attributes): Payment
    {
        return DB::transaction(function () use ($attributes) {
            $allowOverpayment = (bool) ($attributes['allow_overpayment'] ?? false);
            unset($attributes['allow_overpayment']);

            $this->assertUniqueReference($attributes);
            $attributes['is_overpayment'] = $this->resolveOverpayment(
                invoiceId: $attributes['invoice_id'] ?? null,
                amount: (int) ($attributes['amount'] ?? 0),
                allowOverpayment: $allowOverpayment,
            );
            $attributes['status'] ??= 'pending';

            /** @var Payment $payment */
            $payment = parent::create($attributes);
            $this->syncLinkedTotals($payment->client_id, $payment->invoice_id);
            $this->postIncomeToLedger($payment);

            $client = $payment->client_id ? Client::find($payment->client_id) : null;
            $this->alerts->trigger('payment_received', [
                'client_id' => $payment->client_id,
                'client_reference' => $client?->reference_no,
                'client_name' => $client?->full_name,
                'payment_id' => $payment->id,
                'amount' => number_format((int) $payment->amount),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'invoice_id' => $payment->invoice_id,
            ], "payment-{$payment->id}");

            return $payment;
        });
    }

    public function update(Model $model, array $attributes): Payment
    {
        return DB::transaction(function () use ($model, $attributes) {
            /** @var Payment $model */
            $allowOverpayment = (bool) ($attributes['allow_overpayment'] ?? false);
            unset($attributes['allow_overpayment']);

            // The link may change on edit - remember the old targets so their
            // totals get recalculated too, not just the new ones.
            $oldClientId = $model->client_id;
            $oldInvoiceId = $model->invoice_id;

            $this->assertUniqueReference($attributes, $model->id);
            $attributes['is_overpayment'] = $this->resolveOverpayment(
                invoiceId: $attributes['invoice_id'] ?? $model->invoice_id,
                amount: (int) ($attributes['amount'] ?? $model->amount),
                allowOverpayment: $allowOverpayment,
                ignorePaymentId: $model->id,
            );

            /** @var Payment $payment */
            $payment = parent::update($model, $attributes);

            $this->syncLinkedTotals($oldClientId, $oldInvoiceId);
            $this->syncLinkedTotals($payment->client_id, $payment->invoice_id);

            return $payment;
        });
    }

    public function delete(Model $model): bool
    {
        return DB::transaction(function () use ($model) {
            /** @var Payment $model */
            $clientId = $model->client_id;
            $invoiceId = $model->invoice_id;

            $result = parent::delete($model);

            $this->syncLinkedTotals($clientId, $invoiceId);

            return $result;
        });
    }

    public function verify(Payment $payment, int $verifiedBy, ?string $notes = null): Payment
    {
        $payment->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_notes' => $notes,
        ])->save();

        return $payment->refresh();
    }

    public function reject(Payment $payment, int $rejectedBy, ?string $notes = null): Payment
    {
        $payment->forceFill([
            'status' => 'rejected',
            'verified_at' => now(),
            'verified_by' => $rejectedBy,
            'verification_notes' => $notes,
        ])->save();

        return $payment->refresh();
    }

    /**
     * Upload a receipt document and link it to the payment, filing it under the
     * client's Payments folder (or a shared Payment Receipts folder when the
     * payment has no client).
     */
    public function attachReceipt(Payment $payment, UploadedFile $file, FileService $files, int $userId): Payment
    {
        $folder = $this->receiptFolder($payment->client_id ? Client::find($payment->client_id) : null, $userId);

        $stored = $payment->client_id
            ? $files->uploadForClientFolder($file, $payment->client_id, $folder->id, $userId)
            : $files->upload($file, $folder->id, $userId);

        $payment->forceFill(['receipt_file_id' => $stored->id])->save();

        return $payment->refresh();
    }

    public function receiptFolder(?Client $client, int $userId): Folder
    {
        if (! $client) {
            return Folder::firstOrCreate(
                ['name' => 'Payment Receipts', 'parent_id' => null],
                ['slug' => 'payment-receipts', 'is_active' => true, 'created_by' => $userId],
            );
        }

        $root = Folder::firstOrCreate(['name' => 'Clients', 'parent_id' => null], [
            'slug' => 'clients',
            'is_active' => true,
            'created_by' => $userId,
        ]);

        $clientFolder = Folder::where('parent_id', $root->id)
            ->where('name', 'like', $client->reference_no.'%')
            ->first();

        if (! $clientFolder) {
            $name = trim($client->reference_no.' - '.$client->full_name);
            $clientFolder = Folder::create([
                'name' => $name,
                'slug' => Str::slug($name) ?: 'client-'.$client->id,
                'parent_id' => $root->id,
                'is_active' => true,
                'created_by' => $userId,
            ]);
        }

        return Folder::firstOrCreate(
            ['name' => 'Payments', 'parent_id' => $clientFolder->id],
            ['slug' => Str::slug($clientFolder->name.' Payments'), 'is_active' => true, 'created_by' => $userId],
        );
    }

    /**
     * Reject a duplicate reference on the same invoice (a common double-entry
     * mistake). Empty references are allowed to repeat.
     */
    private function assertUniqueReference(array $attributes, ?int $ignoreId = null): void
    {
        $reference = trim((string) ($attributes['reference'] ?? ''));
        $invoiceId = $attributes['invoice_id'] ?? null;

        if ($reference === '' || ! $invoiceId) {
            return;
        }

        $exists = Payment::where('invoice_id', $invoiceId)
            ->where('reference', $reference)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reference' => 'A payment with this reference already exists for this invoice.',
            ]);
        }
    }

    /**
     * Returns whether this payment tips the invoice past its payable total.
     * Throws unless the caller explicitly confirmed the overpayment.
     */
    private function resolveOverpayment(?int $invoiceId, int $amount, bool $allowOverpayment, ?int $ignorePaymentId = null): bool
    {
        if (! $invoiceId) {
            return false;
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return false;
        }

        $existing = (int) $invoice->payments()
            ->when($ignorePaymentId, fn ($q) => $q->where('id', '!=', $ignorePaymentId))
            ->sum('amount');

        $payable = $invoice->total_payable;
        $isOverpayment = ($existing + $amount) > $payable;

        if ($isOverpayment && ! $allowOverpayment) {
            $over = ($existing + $amount) - $payable;
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'This payment exceeds the invoice balance by LKR %s. Confirm the overpayment to continue.',
                    number_format($over)
                ),
            ]);
        }

        return $isOverpayment;
    }

    /**
     * Post a matching income entry into the immutable finance ledger so every
     * recorded payment shows up in the books. Idempotent per payment.
     */
    private function postIncomeToLedger(Payment $payment): void
    {
        if (LedgerEntry::where('source', 'payment')->where('payment_id', $payment->id)->exists()) {
            return;
        }

        LedgerEntry::create([
            'type' => 'income',
            'category' => 'client_payment',
            'amount' => (int) $payment->amount,
            'payment_method' => $this->ledgerMethod($payment->method),
            'source' => 'payment',
            'payment_id' => $payment->id,
            'description' => trim('Payment '.($payment->reference ? "#{$payment->reference}" : "for payment {$payment->id}")),
            'is_locked' => false,
            'entry_date' => $payment->paid_at,
            'recorded_by' => $payment->recorded_by,
        ]);
    }

    private function ledgerMethod(?string $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'cash' => 'cash',
            'bank transfer', 'bank', 'cheque' => 'bank',
            'online', 'card' => 'online',
            default => 'other',
        };
    }

    /**
     * Recompute a client's collected total and an invoice's paid status from
     * the sum of their payments. Recomputing (rather than incrementing) keeps
     * the numbers correct through edits and deletes, not just fresh inserts.
     */
    private function syncLinkedTotals(?int $clientId, ?int $invoiceId): void
    {
        if ($clientId) {
            $client = Client::find($clientId);
            if ($client) {
                $client->forceFill(['paid_amount' => (int) $client->payments()->sum('amount')])->save();
            }
        }

        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice && ! in_array($invoice->status, self::TERMINAL_INVOICE_STATUSES, true)) {
                $paid = (int) $invoice->payments()->sum('amount');
                $payable = $invoice->total_payable;

                $status = match (true) {
                    $payable > 0 && $paid >= $payable => 'paid',
                    $paid > 0 => 'partially_paid',
                    // Back to unpaid: only reset the financial statuses, leave a
                    // manually-set draft/issued as the operator left it.
                    in_array($invoice->status, ['paid', 'partial', 'partially_paid'], true) => 'draft',
                    default => $invoice->status,
                };

                $invoice->forceFill(['status' => $status])->save();
            }
        }
    }
}
