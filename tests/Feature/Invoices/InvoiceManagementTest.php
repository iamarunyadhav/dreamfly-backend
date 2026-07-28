<?php

namespace Tests\Feature\Invoices;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Files\Models\File;
use Modules\Invoices\Models\Invoice;
use Modules\Payments\Models\Payment;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-900-2026',
            'full_name' => 'Invoice Client',
            'passport_no' => 'P900',
            'phone' => '+94760000900',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 250000,
            'paid_amount' => 0,
        ]);
    }

    public function test_invoice_totals_pdf_share_and_multiple_payment_status(): void
    {
        $user = $this->user(['invoices.create', 'invoices.generate', 'invoices.share']);
        $client = $this->client();

        $create = $this->actingAs($user)->postJson('/api/v1/invoices', [
            'client_id' => $client->id,
            'total_service_fee' => 250000,
            'advance_paid' => 50000,
            'application_fee' => 10000,
            'vfs_fee' => 7500,
            'issue_date' => '2026-07-22',
            'due_date' => '2026-07-30',
            'items' => [
                ['description' => 'Extra police clearance', 'quantity' => 2, 'unit_price' => 5000, 'category' => 'extra_charge'],
                ['label' => 'Courier', 'amount' => 2500, 'tax' => 0],
            ],
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.total_payable', 230000);
        $invoice = Invoice::first();
        $this->assertSame(2, $invoice->items()->count());

        $pdf = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/generate-pdf");
        $pdf->assertCreated();
        $pdf->assertJsonPath('data.file.extension', 'pdf');
        $this->assertSame($invoice->id, Invoice::first()->id);
        $this->assertNotNull(Invoice::first()->generated_file_id);
        $this->assertFileExists(storage_path('app/private/'.File::first()->path));

        $share = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/share", [
            'channel' => 'whatsapp',
            'recipient' => $client->phone,
            'body' => 'Invoice notice',
        ]);
        $share->assertCreated();
        $share->assertJsonPath('data.client_id', $client->id);
        $this->assertStringContainsString('signature=', $share->json('data.body'));

        Payment::create(['client_id' => $client->id, 'invoice_id' => $invoice->id, 'amount' => 100000, 'paid_at' => '2026-07-23']);
        app(\Modules\Payments\Services\PaymentService::class)->create([
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 130000,
            'paid_at' => '2026-07-24',
        ]);

        $this->assertSame('paid', $invoice->refresh()->status);
    }

    private function invoice(Client $client, int $userId, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $client->id,
            'reference_no' => 'DF-INV-TEST-'.uniqid(),
            'issue_date' => '2026-07-22',
            'due_date' => '2026-07-30',
            'total_service_fee' => 100000,
            'advance_paid' => 0,
            'application_fee' => 0,
            'vfs_fee' => 0,
            'status' => 'issued',
            'created_by' => $userId,
        ], $overrides));
    }

    public function test_record_payment_endpoint_marks_invoice_paid(): void
    {
        $user = $this->user(['invoices.record_payment']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id); // payable 100000

        $response = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 100000,
            'method' => 'Bank Transfer',
            'reference' => 'TXN-1',
            'paid_at' => '2026-07-25',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.invoice.status', 'paid');
        $response->assertJsonPath('data.payment.status', 'pending');
        $this->assertSame(100000, (int) $invoice->refresh()->paid_amount);
    }

    public function test_overpayment_requires_confirmation(): void
    {
        $user = $this->user(['invoices.record_payment']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id); // payable 100000

        $blocked = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 150000,
            'paid_at' => '2026-07-25',
        ]);
        $blocked->assertStatus(422);
        $blocked->assertJsonValidationErrors('amount');
        $this->assertSame(0, $invoice->refresh()->payments()->count());

        $confirmed = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 150000,
            'paid_at' => '2026-07-25',
            'allow_overpayment' => true,
        ]);
        $confirmed->assertCreated();
        $confirmed->assertJsonPath('data.payment.is_overpayment', true);
        $confirmed->assertJsonPath('data.invoice.status', 'paid');
    }

    public function test_duplicate_reference_is_rejected(): void
    {
        $user = $this->user(['invoices.record_payment']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id);

        $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 40000,
            'reference' => 'DUP-1',
            'paid_at' => '2026-07-25',
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 40000,
            'reference' => 'DUP-1',
            'paid_at' => '2026-07-26',
        ])->assertStatus(422)->assertJsonValidationErrors('reference');
    }

    public function test_payment_can_be_verified(): void
    {
        $user = $this->user(['invoices.record_payment', 'payments.verify']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id);

        $recorded = $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 50000,
            'paid_at' => '2026-07-25',
        ]);
        $paymentId = $recorded->json('data.payment.id');

        $verify = $this->actingAs($user)->postJson("/api/v1/payments/{$paymentId}/verify", [
            'verification_notes' => 'Bank statement checked.',
        ]);
        $verify->assertOk();
        $verify->assertJsonPath('data.status', 'verified');

        $payment = Payment::find($paymentId);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame($user->id, $payment->verified_by);
    }

    public function test_status_transition_to_waived_survives_new_payment(): void
    {
        $user = $this->user(['invoices.edit', 'invoices.record_payment']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id);

        $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/status", [
            'status' => 'waived',
            'reason' => 'Goodwill.',
        ])->assertOk()->assertJsonPath('data.status', 'waived');

        // A payment against a terminal invoice must not flip it back to paid.
        $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 100000,
            'paid_at' => '2026-07-25',
        ])->assertCreated();

        $this->assertSame('waived', $invoice->refresh()->status);
    }

    public function test_cannot_revert_to_draft_with_payments(): void
    {
        $user = $this->user(['invoices.edit', 'invoices.record_payment']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id);

        $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/record-payment", [
            'amount' => 20000,
            'paid_at' => '2026-07-25',
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/invoices/{$invoice->id}/status", [
            'status' => 'draft',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_overdue_command_flags_past_due_invoices(): void
    {
        $user = $this->user(['invoices.create']);
        $client = $this->client();
        $invoice = $this->invoice($client, $user->id, [
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'issued',
        ]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame('overdue', $invoice->refresh()->status);
    }
}
