<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Modules\Finance\Models\DailyClosing;
use Modules\Finance\Models\LedgerEntry;
use Tests\TestCase;

class DailyClosingTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function entry(User $user, string $type, int $amount, string $method, string $date = '2026-07-23'): LedgerEntry
    {
        return LedgerEntry::create([
            'type' => $type, 'category' => 'test', 'amount' => $amount, 'payment_method' => $method,
            'source' => 'manual', 'entry_date' => $date, 'recorded_by' => $user->id,
        ]);
    }

    public function test_compute_aggregates_the_days_ledger(): void
    {
        $user = $this->user('finance.view');
        $this->entry($user, 'income', 60000, 'cash');
        $this->entry($user, 'income', 40000, 'bank');
        $this->entry($user, 'expense', 15000, 'cash');

        $response = $this->actingAs($user)->getJson('/api/v1/finance/daily-closings/compute?date=2026-07-23');
        $response->assertOk();
        $response->assertJsonPath('data.income_total', 100000);
        $response->assertJsonPath('data.expense_total', 15000);
        $response->assertJsonPath('data.cash_total', 45000); // 60000 - 15000
        $response->assertJsonPath('data.bank_total', 40000);
        $response->assertJsonPath('data.closing_balance', 85000);
    }

    public function test_close_locks_entries_and_records_variance(): void
    {
        $user = $this->user(['finance.view', 'finance.edit']);
        $this->entry($user, 'income', 60000, 'cash');
        $this->entry($user, 'expense', 10000, 'cash'); // cash net = 50000

        $response = $this->actingAs($user)->postJson('/api/v1/finance/daily-closings/close', [
            'date' => '2026-07-23', 'counted_cash' => 48000, 'notes' => 'Short by 2000',
        ]);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'closed');
        $response->assertJsonPath('data.variance', -2000); // 48000 - 50000

        $this->assertTrue(LedgerEntry::whereDate('entry_date', '2026-07-23')->get()->every(fn ($e) => $e->is_locked));
    }

    public function test_cannot_edit_entry_once_day_is_closed(): void
    {
        $user = $this->user(['finance.view', 'finance.edit']);
        $entry = $this->entry($user, 'expense', 5000, 'cash');

        $this->actingAs($user)->postJson('/api/v1/finance/daily-closings/close', ['date' => '2026-07-23'])->assertOk();

        $this->actingAs($user)->putJson("/api/v1/finance/ledger/{$entry->id}", ['amount' => 1])
            ->assertStatus(422);
    }

    public function test_reopen_unlocks_entries(): void
    {
        $user = $this->user(['finance.view', 'finance.edit']);
        $entry = $this->entry($user, 'income', 5000, 'cash');
        $this->actingAs($user)->postJson('/api/v1/finance/daily-closings/close', ['date' => '2026-07-23'])->assertOk();

        $closing = DailyClosing::first();
        $this->actingAs($user)->postJson("/api/v1/finance/daily-closings/{$closing->id}/reopen", ['reason' => 'Late entry'])
            ->assertOk()->assertJsonPath('data.status', 'open');

        $this->assertFalse($entry->refresh()->is_locked);
    }

    public function test_opening_balance_carries_from_previous_closed_day(): void
    {
        $user = $this->user(['finance.view', 'finance.edit']);
        $this->entry($user, 'income', 30000, 'cash', '2026-07-22');
        $this->actingAs($user)->postJson('/api/v1/finance/daily-closings/close', ['date' => '2026-07-22'])->assertOk();

        $this->entry($user, 'income', 20000, 'bank', '2026-07-23');
        $response = $this->actingAs($user)->getJson('/api/v1/finance/daily-closings/compute?date=2026-07-23');
        $response->assertOk();
        $response->assertJsonPath('data.opening_balance', 30000);
        $response->assertJsonPath('data.closing_balance', 50000);
    }

    public function test_send_to_admin_generates_pdf_and_notifies_admin_roles(): void
    {
        $user = $this->user(['finance.view', 'finance.edit']);
        $this->entry($user, 'income', 10000, 'cash');
        $this->actingAs($user)->postJson('/api/v1/finance/daily-closings/close', ['date' => '2026-07-23'])->assertOk();
        $closing = DailyClosing::first();

        $response = $this->actingAs($user)->postJson("/api/v1/finance/daily-closings/{$closing->id}/send-to-admin");

        $response->assertOk();
        $this->assertNotNull($closing->refresh()->generated_file_id);
        $this->assertNotNull($closing->sent_to_admin_at);
        $this->assertSame($user->id, $closing->sent_to_admin_by);

        $this->assertDatabaseHas('notifications', ['role' => 'Super Admin', 'type' => 'daily_closing.sent']);
        $this->assertDatabaseHas('notifications', ['role' => 'Admin', 'type' => 'daily_closing.sent']);
    }

    public function test_an_open_day_cannot_be_sent_to_admin(): void
    {
        $user = $this->user(['finance.view', 'finance.edit']);
        $this->entry($user, 'income', 10000, 'cash');
        $this->actingAs($user)->postJson('/api/v1/finance/daily-closings/close', ['date' => '2026-07-23'])->assertOk();
        $closing = DailyClosing::first();
        $this->actingAs($user)->postJson("/api/v1/finance/daily-closings/{$closing->id}/reopen", ['reason' => 'test'])->assertOk();

        $this->actingAs($user)->postJson("/api/v1/finance/daily-closings/{$closing->id}/send-to-admin")
            ->assertStatus(422);
    }
}
