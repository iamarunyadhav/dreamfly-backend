<?php

namespace Tests\Feature\Workflows;

use Illuminate\Support\Facades\Artisan;
use Modules\Clients\Models\Client;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;

/**
 * The Documentation Unit stage was inserted into DEFAULT_STAGES after
 * Correction Unit, but that alone only helps clients initialized fresh.
 * These tests cover `case-steps:insert-document-prep-unit`, the one-off
 * backfill for clients whose case_steps predate the insertion.
 */
class CaseStepInsertDocumentPrepUnitCommandTest extends TestCase
{
    /** The pre-2026-08-10 9-stage layout, built by hand so it does NOT
     *  include document_prep_unit - exactly what a live client looked like
     *  before this migration. */
    private const LEGACY_KEYS = [
        'admin_summary', 'application_unit', 'documentation_unit', 'supervisor_review',
        'responsibility_notice', 'invoice', 'submission', 'visa_result', 'closed',
    ];

    private function legacyClient(string $currentKey): Client
    {
        // Fixed, not random - each test method only calls this once, and a
        // random suffix risked colliding with the fixed 'DF-899-2026' used
        // for the no-steps client in the idempotency test below.
        $client = Client::create([
            'reference_no' => 'DF-800-2026',
            'full_name' => 'Legacy Backfill Client',
            'service_category' => 'visit_visa',
            'current_stage' => $currentKey,
            'status' => 'active',
        ]);

        $currentIndex = array_search($currentKey, self::LEGACY_KEYS, true);

        foreach (self::LEGACY_KEYS as $index => $key) {
            $status = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'in_progress' : 'pending');
            CaseStep::create([
                'client_id' => $client->id,
                'key' => $key,
                'name' => $key,
                'order' => $index,
                'owner_role' => 'Correction Unit Staff',
                'status' => $status,
                'requires_checklist' => false,
                'started_at' => $index <= $currentIndex ? now() : null,
                'completed_at' => $index < $currentIndex ? now() : null,
            ]);
        }

        return $client;
    }

    private function orderedKeys(Client $client): array
    {
        return CaseStep::where('client_id', $client->id)->orderBy('order')->pluck('key')->all();
    }

    public function test_inserts_both_pending_stages_for_a_client_not_yet_at_correction_unit(): void
    {
        $client = $this->legacyClient('application_unit');

        Artisan::call('case-steps:insert-document-prep-unit');

        $this->assertSame(11, CaseStep::where('client_id', $client->id)->count());
        $this->assertSame(
            ['admin_summary', 'application_unit', 'documentation_unit', 'document_prep_unit', 'upload_team', 'supervisor_review', 'responsibility_notice', 'invoice', 'submission', 'visa_result', 'closed'],
            $this->orderedKeys($client),
        );
        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'document_prep_unit')->value('status'));
        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'upload_team')->value('status'));
        $this->assertSame('application_unit', $client->refresh()->current_stage);
    }

    public function test_inserts_both_pending_stages_for_a_client_still_working_correction_unit(): void
    {
        $client = $this->legacyClient('documentation_unit');

        Artisan::call('case-steps:insert-document-prep-unit');

        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'document_prep_unit')->value('status'));
        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'upload_team')->value('status'));
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->value('status'));
        $this->assertSame('documentation_unit', $client->refresh()->current_stage);
    }

    public function test_activates_document_prep_unit_only_at_the_exact_boundary(): void
    {
        $client = $this->legacyClient('supervisor_review');

        Artisan::call('case-steps:insert-document-prep-unit');

        $this->assertSame('completed', CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->value('status'));
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'document_prep_unit')->value('status'));
        // Upload Team does not activate too - only one step is ever "current".
        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'upload_team')->value('status'));
        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'supervisor_review')->value('status'));
        $this->assertSame('document_prep_unit', $client->refresh()->current_stage);

        // Every step from the old supervisor_review onward moved up two order slots to make room for both new stages.
        $this->assertSame(5, CaseStep::where('client_id', $client->id)->where('key', 'supervisor_review')->value('order'));
        $this->assertSame(10, CaseStep::where('client_id', $client->id)->where('key', 'closed')->value('order'));
    }

    public function test_marks_both_stages_already_completed_for_a_client_already_past_them(): void
    {
        $client = $this->legacyClient('invoice');

        Artisan::call('case-steps:insert-document-prep-unit');

        $this->assertSame('completed', CaseStep::where('client_id', $client->id)->where('key', 'document_prep_unit')->value('status'));
        $this->assertSame('completed', CaseStep::where('client_id', $client->id)->where('key', 'upload_team')->value('status'));
        // Finished progress is never retroactively blocked or rewound.
        $this->assertSame('invoice', $client->refresh()->current_stage);
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'invoice')->value('status'));
    }

    public function test_is_idempotent_and_skips_clients_with_no_case_steps(): void
    {
        $client = $this->legacyClient('application_unit');
        $noStepsClient = Client::create([
            'reference_no' => 'DF-899-2026',
            'full_name' => 'No Steps Client',
            'service_category' => 'visit_visa',
            'current_stage' => 'admin_summary',
            'status' => 'active',
        ]);

        Artisan::call('case-steps:insert-document-prep-unit');
        Artisan::call('case-steps:insert-document-prep-unit');

        $this->assertSame(11, CaseStep::where('client_id', $client->id)->count());
        $this->assertSame(1, CaseStep::where('client_id', $client->id)->where('key', 'document_prep_unit')->count());
        $this->assertSame(1, CaseStep::where('client_id', $client->id)->where('key', 'upload_team')->count());
        $this->assertSame(0, CaseStep::where('client_id', $noStepsClient->id)->count());
    }
}
