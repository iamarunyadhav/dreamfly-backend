<?php

namespace Tests\Feature\Workflows;

use Illuminate\Support\Facades\Artisan;
use Modules\Clients\Models\Client;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;
use Tests\TestCase;

class CaseStepBackfillCommandTest extends TestCase
{
    public function test_backfill_seeds_case_steps_for_clients_that_have_none(): void
    {
        // Created via the bare Eloquent model, exactly like a client from
        // before the runtime engine was wired into ClientService::create().
        $legacyClient = Client::create([
            'reference_no' => 'DF-720-2026',
            'full_name' => 'Legacy Client',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
        ]);

        $this->assertSame(0, CaseStep::where('client_id', $legacyClient->id)->count());

        Artisan::call('case-steps:backfill');

        $this->assertSame(9, CaseStep::where('client_id', $legacyClient->id)->count());
        $this->assertSame(
            'in_progress',
            CaseStep::where('client_id', $legacyClient->id)->where('key', 'documentation_unit')->value('status')
        );
    }

    public function test_backfill_does_not_duplicate_steps_for_clients_that_already_have_them(): void
    {
        $client = Client::create([
            'reference_no' => 'DF-721-2026',
            'full_name' => 'Already Seeded Client',
            'service_category' => 'visit_visa',
            'current_stage' => 'admin_summary',
            'status' => 'active',
        ]);
        app(CaseStepService::class)->initializeForClient($client);
        $this->assertSame(9, CaseStep::where('client_id', $client->id)->count());

        Artisan::call('case-steps:backfill');

        $this->assertSame(9, CaseStep::where('client_id', $client->id)->count());
    }
}
