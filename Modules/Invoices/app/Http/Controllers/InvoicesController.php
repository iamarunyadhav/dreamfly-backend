<?php

namespace Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;
use Modules\Files\Services\FileService;
use Modules\Invoices\Http\Requests\StoreInvoiceRequest;
use Modules\Invoices\Http\Requests\UpdateInvoiceRequest;
use Modules\Invoices\Http\Resources\InvoiceResource;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Services\InvoiceDocumentService;
use Modules\Invoices\Services\InvoiceService;
use Modules\Payments\Http\Resources\PaymentResource;
use Modules\Payments\Services\PaymentService;

class InvoicesController extends Controller
{
    use ApiResponse;

    public function __construct(protected InvoiceService $service)
    {
    }

    public function index(Request $request)
    {
        $invoices = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['items', 'payments', 'generatedFile'],
            filters: $request->only(['search', 'client_id', 'status']),
        );

        return $this->ok(InvoiceResource::collection($invoices));
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new InvoiceResource($invoice->load('items', 'payments', 'generatedFile')));
    }

    public function show(Invoice $invoice)
    {
        return $this->ok(new InvoiceResource($invoice->load('items', 'payments', 'generatedFile')));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $invoice = $this->service->update($invoice, $request->validated());

        return $this->ok(new InvoiceResource($invoice->load('items', 'payments', 'generatedFile')), 'Invoice updated successfully.');
    }

    public function generatePdf(Request $request, Invoice $invoice, InvoiceDocumentService $documents)
    {
        $file = $documents->generatePdf($invoice, $request->user()->id);

        return $this->created([
            'invoice' => new InvoiceResource($invoice->refresh()->load('items', 'payments', 'generatedFile')),
            'file' => new \Modules\Files\Http\Resources\FileResource($file),
        ], 'Invoice PDF generated and saved.');
    }

    public function share(Request $request, Invoice $invoice, InvoiceDocumentService $documents, MessageService $messages)
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $file = $invoice->generatedFile ?: $documents->generatePdf($invoice, $request->user()->id);
        $message = $messages->send([
            ...$validated,
            'client_id' => $invoice->client_id,
            'workflow_step' => 'invoice',
            'subject' => $validated['subject'] ?? $invoice->reference_no.' Invoice Notice',
            'body' => trim($validated['body'] ?? 'Please review your invoice notice.')."\n\nAttachment: ".URL::temporarySignedRoute(
                'api.files.signed-download',
                now()->addDays(7),
                $file->id
            ),
        ], $request->user()->id);

        if ($invoice->status === 'draft') {
            $invoice->forceFill(['status' => 'issued'])->save();
        }

        return $this->created(new MessageResource($message), 'Invoice shared and recorded.');
    }

    public function recordPayment(Request $request, Invoice $invoice, PaymentService $payments, FileService $files)
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'allow_overpayment' => ['sometimes', 'boolean'],
            'receipt' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $payment = $payments->create([
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'amount' => (int) $validated['amount'],
            'method' => $validated['method'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now()->toDateString(),
            'allow_overpayment' => (bool) ($validated['allow_overpayment'] ?? false),
            'recorded_by' => $request->user()->id,
        ]);

        if ($request->hasFile('receipt')) {
            $payment = $payments->attachReceipt($payment, $request->file('receipt'), $files, $request->user()->id);
        }

        return $this->created([
            'invoice' => new InvoiceResource($invoice->refresh()->load('items', 'payments', 'generatedFile')),
            'payment' => new PaymentResource($payment->load('receiptFile')),
        ], 'Payment recorded against invoice.');
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'issued', 'cancelled', 'waived', 'refunded'])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = $validated['status'];

        // Once money is recorded the invoice must stay in a financial status
        // (partially_paid / paid) or move to a terminal one (waived / refunded /
        // cancelled) - never silently back to draft/issued.
        if (in_array($target, ['draft', 'issued'], true) && $invoice->payments()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot revert to draft/issued while payments exist. Use waive, refund, or cancel instead.',
            ]);
        }

        $reason = trim((string) ($validated['reason'] ?? ''));
        $invoice->forceFill([
            'status' => $target,
            'notes' => $reason !== ''
                ? trim(($invoice->notes ? $invoice->notes."\n" : '').'['.ucfirst($target).'] '.$reason)
                : $invoice->notes,
        ])->save();

        return $this->ok(
            new InvoiceResource($invoice->refresh()->load('items', 'payments', 'generatedFile')),
            'Invoice status updated.'
        );
    }

    public function destroy(Invoice $invoice)
    {
        $this->service->delete($invoice);

        return $this->noContent();
    }
}
