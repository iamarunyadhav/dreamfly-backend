<?php

namespace Tests\Feature\Clients;

use Illuminate\Support\Carbon;
use Modules\Clients\Models\AuthorityRequest;
use Modules\Clients\Models\Client;
use Modules\Communications\Models\AlertDispatch;
use Modules\Communications\Models\AlertTemplate;
use Tests\TestCase;

class AuthorityRequestDeadlineTest extends TestCase
{
    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-140-2026',
            'full_name' => 'Authority Request Client',
            'service_category' => 'visit_visa',
            'current_stage' => 'submission',
            'status' => 'active',
        ]);
    }

    public function test_command_reminds_approaching_and_escalates_overdue_requests(): void
    {
        Carbon::setTestNow('2026-07-26 09:00:00');
        $client = $this->client();

        $approaching = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'UK Visas and Immigration',
            'title' => 'Additional bank statements',
            'received_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'status' => 'pending',
        ]);

        $overdue = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'VFS Global',
            'title' => 'Biometrics confirmation',
            'received_at' => now()->subWeek(),
            'due_at' => now()->subDay(),
            'status' => 'in_progress',
        ]);

        $notYet = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'Canadian High Commission',
            'title' => 'Interview scheduling',
            'received_at' => now(),
            'due_at' => now()->addWeek(),
            'status' => 'pending',
        ]);

        $resolved = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'Australian High Commission',
            'title' => 'Medical results',
            'received_at' => now()->subWeek(),
            'due_at' => now()->subDay(),
            'status' => 'responded',
        ]);

        $this->artisan('authority-requests:process-deadlines')->assertSuccessful();

        $this->assertNotNull($approaching->refresh()->reminded_at);
        $this->assertSame('pending', $approaching->status);

        $this->assertSame('overdue', $overdue->refresh()->status);
        $this->assertNotNull($overdue->reminded_at);

        $this->assertNull($notYet->refresh()->reminded_at);
        $this->assertSame('pending', $notYet->status);

        $this->assertSame('responded', $resolved->refresh()->status);
        $this->assertNull($resolved->reminded_at);
    }

    public function test_running_the_command_twice_does_not_double_fire_the_same_event(): void
    {
        Carbon::setTestNow('2026-07-26 09:00:00');
        AlertTemplate::create([
            'name' => 'Authority request overdue',
            'trigger' => 'overdue',
            'channels' => ['internal'],
            'recipient_rules' => ['roles' => ['Supervisor']],
            'is_enabled' => true,
        ]);
        $client = $this->client();
        $request = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'UK Visas and Immigration',
            'title' => 'Additional bank statements',
            'received_at' => now()->subDay(),
            'due_at' => now()->subDay(),
            'status' => 'pending',
        ]);

        $this->artisan('authority-requests:process-deadlines')->assertSuccessful();
        $this->artisan('authority-requests:process-deadlines')->assertSuccessful();

        $this->assertSame(
            1,
            AlertDispatch::where('trigger', 'overdue')->where('dedupe_key', "authority-request-{$request->id}-overdue")->count(),
        );
    }
}
