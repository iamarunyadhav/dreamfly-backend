<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Files\Services\FileService;
use Modules\Payments\Http\Requests\StorePaymentRequest;
use Modules\Payments\Http\Requests\UpdatePaymentRequest;
use Modules\Payments\Http\Resources\PaymentResource;
use Modules\Payments\Models\Payment;
use Modules\Payments\Services\PaymentService;

class PaymentsController extends Controller
{
    use ApiResponse;

    public function __construct(protected PaymentService $service)
    {
    }

    public function index(Request $request)
    {
        $payments = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['receiptFile'],
            filters: $request->only(['client_id', 'common_user_id', 'agreement_id', 'invoice_id', 'method', 'status']),
        );

        return $this->ok(PaymentResource::collection($payments));
    }

    public function store(StorePaymentRequest $request)
    {
        $payment = $this->service->create([...$request->validated(), 'recorded_by' => $request->user()->id]);

        return $this->created(new PaymentResource($payment->load('receiptFile')));
    }

    public function show(Payment $payment)
    {
        return $this->ok(new PaymentResource($payment->load('receiptFile')));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $payment = $this->service->update($payment, $request->validated());

        return $this->ok(new PaymentResource($payment->load('receiptFile')), 'Payment updated successfully.');
    }

    public function verify(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $this->service->verify($payment, $request->user()->id, $validated['verification_notes'] ?? null);

        return $this->ok(new PaymentResource($payment->load('receiptFile')), 'Payment verified.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $this->service->reject($payment, $request->user()->id, $validated['verification_notes'] ?? null);

        return $this->ok(new PaymentResource($payment->load('receiptFile')), 'Payment marked as rejected.');
    }

    public function uploadReceipt(Request $request, Payment $payment, FileService $files)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $payment = $this->service->attachReceipt($payment, $request->file('file'), $files, $request->user()->id);

        return $this->created(new PaymentResource($payment->load('receiptFile')), 'Receipt uploaded and linked.');
    }

    public function destroy(Payment $payment)
    {
        $this->service->delete($payment);

        return $this->noContent();
    }
}
