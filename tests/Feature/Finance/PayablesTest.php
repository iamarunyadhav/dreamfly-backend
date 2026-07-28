<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Models\Payable;
use Tests\TestCase;

class PayablesTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_a_payable_can_be_created_pending(): void
    {
        $user = $this->user('finance.create');

        $response = $this->actingAs($user)->postJson('/api/v1/finance/payables', [
            'payee' => 'VFS Global',
            'category' => 'vfs_fee',
            'amount' => 15000,
            'due_date' => '2026-08-05',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('payables', ['payee' => 'VFS Global', 'status' => 'pending']);
    }

    public function test_paying_a_payable_posts_an_immutable_expense_entry(): void
    {
        $user = $this->user(['finance.create', 'finance.edit']);
        $payable = Payable::create([
            'payee' => 'VFS Global', 'category' => 'vfs_fee', 'amount' => 15000, 'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/finance/payables/{$payable->id}/pay", [
            'payment_method' => 'bank',
            'reference' => 'TXN-99',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'paid');

        $entry = LedgerEntry::where('source', 'payable')->first();
        $this->assertNotNull($entry);
        $this->assertSame('expense', $entry->type);
        $this->assertSame('vfs_fee', $entry->category);
        $this->assertSame(15000, $entry->amount);
        $this->assertFalse($entry->isEditable());

        $this->assertSame($entry->id, $payable->fresh()->ledger_entry_id);
    }

    public function test_a_paid_payable_cannot_be_paid_again(): void
    {
        $user = $this->user(['finance.create', 'finance.edit']);
        $payable = Payable::create([
            'payee' => 'Landlord', 'category' => 'rent', 'amount' => 40000, 'status' => 'pending',
        ]);
        app(\Modules\Finance\Services\PayableService::class)->pay($payable, ['payment_method' => 'cash'], $user->id);

        $response = $this->actingAs($user)->postJson("/api/v1/finance/payables/{$payable->id}/pay", [
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, LedgerEntry::where('source', 'payable')->count());
    }

    public function test_a_paid_payable_cannot_be_edited_or_deleted(): void
    {
        $user = $this->user(['finance.create', 'finance.edit', 'finance.delete']);
        $payable = Payable::create([
            'payee' => 'Landlord', 'category' => 'rent', 'amount' => 40000, 'status' => 'pending',
        ]);
        app(\Modules\Finance\Services\PayableService::class)->pay($payable, ['payment_method' => 'cash'], $user->id);

        $this->actingAs($user)->putJson("/api/v1/finance/payables/{$payable->id}", ['amount' => 1])
            ->assertStatus(422);
        $this->actingAs($user)->deleteJson("/api/v1/finance/payables/{$payable->id}")
            ->assertStatus(422);
    }

    public function test_cancelling_a_payable_records_the_reason_without_touching_the_ledger(): void
    {
        $user = $this->user(['finance.create', 'finance.edit']);
        $payable = Payable::create([
            'payee' => 'Old Vendor', 'category' => 'other', 'amount' => 5000, 'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/finance/payables/{$payable->id}/cancel", [
            'reason' => 'Duplicate entry, vendor never billed us.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(0, LedgerEntry::where('source', 'payable')->count());
    }

    public function test_summary_reports_outstanding_and_overdue_payables(): void
    {
        $user = $this->user('finance.view');
        Payable::create(['payee' => 'A', 'category' => 'other', 'amount' => 1000, 'status' => 'pending', 'due_date' => now()->subDay()]);
        Payable::create(['payee' => 'B', 'category' => 'other', 'amount' => 2000, 'status' => 'pending', 'due_date' => now()->addWeek()]);
        Payable::create(['payee' => 'C', 'category' => 'other', 'amount' => 9000, 'status' => 'paid']);

        $response = $this->actingAs($user)->getJson('/api/v1/finance/payables/summary');

        $response->assertOk();
        $response->assertJsonPath('data.total_outstanding', 3000);
        $response->assertJsonPath('data.count', 2);
        $response->assertJsonPath('data.overdue_count', 1);
    }

    public function test_index_filters_by_status_and_category(): void
    {
        $user = $this->user('finance.view');
        Payable::create(['payee' => 'A', 'category' => 'rent', 'amount' => 1000, 'status' => 'pending']);
        Payable::create(['payee' => 'B', 'category' => 'vfs_fee', 'amount' => 2000, 'status' => 'paid']);

        $response = $this->actingAs($user)->getJson('/api/v1/finance/payables?status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('A', $response->json('data.0.payee'));
    }
}
