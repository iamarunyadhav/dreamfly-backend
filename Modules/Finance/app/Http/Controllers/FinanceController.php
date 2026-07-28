<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Http\Requests\StoreLedgerEntryRequest;
use Modules\Finance\Http\Requests\UpdateLedgerEntryRequest;
use Modules\Finance\Http\Resources\LedgerEntryResource;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Services\LedgerEntryService;
use Modules\Invoices\Models\Invoice;

class FinanceController extends Controller
{
    use ApiResponse;

    public function __construct(protected LedgerEntryService $service)
    {
    }

    public function index(Request $request)
    {
        $entries = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'type', 'category', 'from', 'to']),
        );

        return $this->ok(LedgerEntryResource::collection($entries));
    }

    public function store(StoreLedgerEntryRequest $request)
    {
        $entry = $this->service->create([...$request->validated(), 'source' => 'manual', 'recorded_by' => $request->user()->id]);

        return $this->created(new LedgerEntryResource($entry));
    }

    public function show(LedgerEntry $ledgerEntry)
    {
        return $this->ok(new LedgerEntryResource($ledgerEntry));
    }

    public function update(UpdateLedgerEntryRequest $request, LedgerEntry $ledgerEntry)
    {
        $ledgerEntry = $this->service->update($ledgerEntry, $request->validated());

        return $this->ok(new LedgerEntryResource($ledgerEntry), 'Ledger entry updated successfully.');
    }

    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'adjusts_entry_id' => ['nullable', 'integer', 'exists:ledger_entries,id'],
            'type' => ['required_without:adjusts_entry_id', 'in:income,expense'],
            'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'in:cash,bank,online,other'],
            'description' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
            'entry_date' => ['nullable', 'date'],
        ]);

        $entry = $this->service->adjust($validated, $request->user()->id);

        return $this->created(new LedgerEntryResource($entry), 'Adjustment recorded.');
    }

    /**
     * Aggregate totals for a date range (financial report): income/expense by
     * category and by payment method, plus the net.
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = LedgerEntry::query()
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('entry_date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('entry_date', '<=', $to));

        $income = (int) (clone $query)->where('type', 'income')->sum('amount');
        $expense = (int) (clone $query)->where('type', 'expense')->sum('amount');

        $byCategory = (clone $query)
            ->select('type', 'category', DB::raw('SUM(amount) as total'))
            ->groupBy('type', 'category')
            ->get();

        $byMethod = (clone $query)
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->where('type', 'income')
            ->groupBy('payment_method')
            ->get();

        return $this->ok([
            'income_total' => $income,
            'expense_total' => $expense,
            'net' => $income - $expense,
            'by_category' => $byCategory,
            'income_by_method' => $byMethod,
        ]);
    }

    /**
     * Outstanding client receivables, derived from invoices with a balance.
     */
    public function receivables()
    {
        $invoices = Invoice::with('client')
            ->whereNotIn('status', ['cancelled', 'waived', 'refunded'])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance > 0)
            ->map(fn (Invoice $invoice) => [
                'invoice_id' => $invoice->id,
                'reference_no' => $invoice->reference_no,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client?->full_name,
                'total_payable' => $invoice->total_payable,
                'paid_amount' => $invoice->paid_amount,
                'balance' => $invoice->balance,
                'due_date' => $invoice->due_date,
                'status' => $invoice->status,
            ])
            ->values();

        return $this->ok([
            'total_outstanding' => (int) $invoices->sum('balance'),
            'count' => $invoices->count(),
            'items' => $invoices,
        ]);
    }

    public function destroy(LedgerEntry $ledgerEntry)
    {
        $this->service->delete($ledgerEntry);

        return $this->noContent();
    }
}
