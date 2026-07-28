<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Finance\Models\LedgerEntry;
use Modules\Invoices\Models\Invoice;
use Modules\Payments\Services\PaymentService;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_recording_a_payment_posts_income_to_the_ledger(): void
    {
        $user = $this->user('payments.create');
        app(PaymentService::class)->create([
            'amount' => 50000,
            'method' => 'Cash',
            'reference' => 'PX-1',
            'paid_at' => '2026-07-23',
            'recorded_by' => $user->id,
        ]);

        $entry = LedgerEntry::where('source', 'payment')->first();
        $this->assertNotNull($entry);
        $this->assertSame('income', $entry->type);
        $this->assertSame(50000, $entry->amount);
        $this->assertSame('cash', $entry->payment_method);
    }

    public function test_system_posted_entry_cannot_be_edited_or_deleted(): void
    {
        $user = $this->user(['payments.create', 'finance.edit', 'finance.delete']);
        app(PaymentService::class)->create(['amount' => 20000, 'method' => 'Bank Transfer', 'paid_at' => '2026-07-23', 'recorded_by' => $user->id]);
        $entry = LedgerEntry::where('source', 'payment')->first();

        $this->actingAs($user)->putJson("/api/v1/finance/ledger/{$entry->id}", ['amount' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('entry');
        $this->actingAs($user)->deleteJson("/api/v1/finance/ledger/{$entry->id}")
            ->assertStatus(422);
    }

    public function test_adjustment_requires_reason_and_records_correction(): void
    {
        $user = $this->user('finance.create');
        $original = LedgerEntry::create([
            'type' => 'expense', 'category' => 'rent', 'amount' => 10000,
            'source' => 'manual', 'entry_date' => '2026-07-23', 'recorded_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson('/api/v1/finance/ledger/adjust', [
            'adjusts_entry_id' => $original->id, 'amount' => 2000,
        ])->assertStatus(422)->assertJsonValidationErrors('reason');

        $ok = $this->actingAs($user)->postJson('/api/v1/finance/ledger/adjust', [
            'adjusts_entry_id' => $original->id, 'amount' => 2000, 'reason' => 'Overstated rent',
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('data.source', 'adjustment');
        $this->assertSame($original->id, LedgerEntry::where('source', 'adjustment')->first()->adjusts_entry_id);
    }

    public function test_summary_report_totals(): void
    {
        $user = $this->user('finance.view');
        LedgerEntry::create(['type' => 'income', 'category' => 'client_payment', 'amount' => 80000, 'source' => 'manual', 'entry_date' => '2026-07-23', 'recorded_by' => $user->id]);
        LedgerEntry::create(['type' => 'expense', 'category' => 'salary', 'amount' => 30000, 'source' => 'manual', 'entry_date' => '2026-07-23', 'recorded_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/finance/summary?from=2026-07-01&to=2026-07-31');
        $response->assertOk();
        $response->assertJsonPath('data.income_total', 80000);
        $response->assertJsonPath('data.expense_total', 30000);
        $response->assertJsonPath('data.net', 50000);
    }

    public function test_receivables_lists_outstanding_invoices(): void
    {
        $user = $this->user('finance.view');
        $client = Client::create([
            'reference_no' => 'DF-500-2026', 'full_name' => 'Rec Client', 'service_category' => 'visit_visa',
            'agreement_amount' => 100000, 'paid_amount' => 0,
        ]);
        Invoice::create([
            'client_id' => $client->id, 'reference_no' => 'DF-INV-REC-1', 'total_service_fee' => 100000,
            'advance_paid' => 0, 'application_fee' => 0, 'vfs_fee' => 0, 'status' => 'issued', 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/finance/receivables');
        $response->assertOk();
        $response->assertJsonPath('data.count', 1);
        $response->assertJsonPath('data.total_outstanding', 100000);
    }
}
