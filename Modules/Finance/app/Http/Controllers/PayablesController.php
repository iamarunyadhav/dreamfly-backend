<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Finance\Http\Requests\StorePayableRequest;
use Modules\Finance\Http\Requests\UpdatePayableRequest;
use Modules\Finance\Http\Resources\PayableResource;
use Modules\Finance\Models\Payable;
use Modules\Finance\Services\PayableService;

class PayablesController extends Controller
{
    use ApiResponse;

    public function __construct(protected PayableService $service)
    {
    }

    public function index(Request $request)
    {
        $payables = Payable::query()
            ->with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('search'), fn ($q) => $q->where('payee', 'like', '%'.$request->string('search').'%'))
            ->orderByRaw("status = 'pending' desc")
            ->orderBy('due_date')
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok(PayableResource::collection($payables));
    }

    public function store(StorePayableRequest $request)
    {
        $payable = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new PayableResource($payable));
    }

    public function show(Payable $payable)
    {
        return $this->ok(new PayableResource($payable->load('client')));
    }

    public function update(UpdatePayableRequest $request, Payable $payable)
    {
        $payable = $this->service->update($payable, $request->validated());

        return $this->ok(new PayableResource($payable), 'Payable updated.');
    }

    public function pay(Request $request, Payable $payable)
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'online', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $payable = $this->service->pay($payable, $validated, $request->user()->id);

        return $this->ok(new PayableResource($payable->load('client')), 'Payable marked paid and posted to the ledger.');
    }

    public function cancel(Request $request, Payable $payable)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $payable = $this->service->cancel($payable, $validated['reason']);

        return $this->ok(new PayableResource($payable), 'Payable cancelled.');
    }

    public function destroy(Payable $payable)
    {
        $this->service->delete($payable);

        return $this->noContent();
    }

    /** Outstanding payables summary - the mirror of finance/receivables. */
    public function summary()
    {
        $pending = Payable::where('status', 'pending')->get();

        return $this->ok([
            'total_outstanding' => (int) $pending->sum('amount'),
            'count' => $pending->count(),
            'overdue_count' => $pending->filter(fn (Payable $p) => $p->is_overdue)->count(),
        ]);
    }
}
