<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\SupervisorReview;
use Modules\Clients\Models\SupervisorReviewComment;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;
use Tests\TestCase;

class SupervisorReviewTest extends TestCase
{
    private const REVIEWER_PERMISSIONS = [
        'supervisor-review.view',
        'supervisor-review.comment',
        'supervisor-review.approve',
        'supervisor-review.send_back',
    ];

    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(string $stage = 'supervisor_review'): Client
    {
        return Client::create([
            'reference_no' => 'DF-512-2026',
            'full_name' => 'Review Stage Client',
            'passport_no' => 'N5120001',
            'phone' => '0771230512',
            'email' => 'review@example.com',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => $stage,
            'status' => 'active',
        ]);
    }

    /** Build the runtime so the review has real steps to approve or send back to. */
    private function withRuntime(Client $client): Client
    {
        app(CaseStepService::class)->initializeForClient($client);

        return $client->refresh();
    }

    public function test_show_returns_no_review_before_the_case_reaches_the_stage(): void
    {
        $user = $this->staff('supervisor-review.view');
        $client = $this->withRuntime($this->client('application_unit'));

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/supervisor-review");

        $response->assertOk();
        $response->assertJsonPath('data.review', null);
        $response->assertJsonPath('data.readiness.is_current_stage', false);
        $this->assertSame(0, SupervisorReview::where('client_id', $client->id)->count());
    }

    public function test_show_opens_round_one_and_reports_readiness_at_the_stage(): void
    {
        $user = $this->staff('supervisor-review.view');
        $client = $this->withRuntime($this->client());

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/supervisor-review");

        $response->assertOk();
        $response->assertJsonPath('data.review.round', 1);
        $response->assertJsonPath('data.review.status', 'pending');
        $response->assertJsonPath('data.readiness.is_current_stage', true);
        $response->assertJsonPath('data.readiness.step_status', 'in_progress');

        // Only stages before Supervisor Review may be sent back to.
        $options = collect($response->json('data.send_back_options'))->pluck('key')->all();
        $this->assertSame(['admin_summary', 'application_unit', 'documentation_unit', 'document_prep_unit', 'upload_team'], $options);

        // Idempotent: a second read must not open a second round.
        $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/supervisor-review")->assertOk();
        $this->assertSame(1, SupervisorReview::where('client_id', $client->id)->count());
    }

    public function test_approve_records_sign_off_and_advances_the_case(): void
    {
        $user = $this->staff(self::REVIEWER_PERMISSIONS);
        $client = $this->withRuntime($this->client());

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/supervisor-review/approve", [
            'decision_notes' => 'File complete, financials verified.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.reviewer_id', $user->id);
        $response->assertJsonPath('data.decision_notes', 'File complete, financials verified.');
        $this->assertNotNull($response->json('data.reviewed_at'));

        $step = CaseStep::where('client_id', $client->id)->where('key', 'supervisor_review')->first();
        $this->assertSame('completed', $step->status);
        $this->assertSame($user->id, $step->completed_by);

        // The case moved on to the next step in the journey.
        $this->assertSame('responsibility_notice', $client->refresh()->current_stage);
        $this->assertSame(
            'in_progress',
            CaseStep::where('client_id', $client->id)->where('key', 'responsibility_notice')->value('status'),
        );
    }

    public function test_a_decided_round_cannot_be_decided_again(): void
    {
        $user = $this->staff(self::REVIEWER_PERMISSIONS);
        $client = $this->withRuntime($this->client());

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/supervisor-review/approve")->assertOk();

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/supervisor-review/approve")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_send_back_reopens_the_target_stage_and_opens_a_new_round(): void
    {
        $user = $this->staff(self::REVIEWER_PERMISSIONS);
        $assignee = User::factory()->create(['status' => 'active']);
        $client = $this->withRuntime($this->client());

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/supervisor-review/send-back", [
            'stage' => 'documentation_unit',
            'reason' => 'Bank statement is older than 3 months - collect a fresh one.',
            'assigned_to_user_id' => $assignee->id,
        ]);

        $response->assertOk();
        // The response carries the freshly opened round, not the decided one.
        $response->assertJsonPath('data.review.round', 2);
        $response->assertJsonPath('data.review.status', 'pending');

        $decided = SupervisorReview::where('client_id', $client->id)->where('round', 1)->first();
        $this->assertSame('sent_back', $decided->status);
        $this->assertSame('documentation_unit', $decided->sent_back_to_stage);
        $this->assertSame($assignee->id, $decided->assigned_to_user_id);
        $this->assertSame($user->id, $decided->reviewer_id);

        // Runtime followed: the target step reopened, later steps reset.
        $steps = CaseStep::where('client_id', $client->id)->get()->keyBy('key');
        $this->assertSame('in_progress', $steps['documentation_unit']->status);
        $this->assertNull($steps['documentation_unit']->completed_at);
        $this->assertSame('pending', $steps['supervisor_review']->status);
        $this->assertSame('completed', $steps['application_unit']->status);
        $this->assertSame('documentation_unit', $client->refresh()->current_stage);
    }

    public function test_send_back_rejects_a_stage_that_is_not_before_the_review(): void
    {
        $user = $this->staff(self::REVIEWER_PERMISSIONS);
        $client = $this->withRuntime($this->client());

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/supervisor-review/send-back", [
                'stage' => 'submission',
                'reason' => 'Wrong direction.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stage');

        $this->assertSame('supervisor_review', $client->refresh()->current_stage);
    }

    public function test_comment_thread_spans_rounds_and_only_the_author_can_delete(): void
    {
        $reviewer = $this->staff(self::REVIEWER_PERMISSIONS);
        $unitStaff = $this->staff(['supervisor-review.view', 'supervisor-review.comment']);
        $client = $this->withRuntime($this->client());

        $this->actingAs($reviewer)
            ->postJson("/api/v1/clients/{$client->id}/supervisor-review/comments", ['body' => 'Financials look thin.'])
            ->assertCreated()
            ->assertJsonPath('data.round', 1);

        $this->actingAs($reviewer)->postJson("/api/v1/clients/{$client->id}/supervisor-review/send-back", [
            'stage' => 'documentation_unit',
            'reason' => 'Collect a fresh bank statement.',
        ])->assertOk();

        $reply = $this->actingAs($unitStaff)
            ->postJson("/api/v1/clients/{$client->id}/supervisor-review/comments", ['body' => 'Fresh statement uploaded.'])
            ->assertCreated()
            ->assertJsonPath('data.round', 2)
            ->json('data.id');

        // The whole back-and-forth reads as one thread across both rounds.
        $thread = $this->actingAs($reviewer)
            ->getJson("/api/v1/clients/{$client->id}/supervisor-review")
            ->assertOk()
            ->json('data.comments');

        $this->assertCount(2, $thread);
        $this->assertSame([1, 2], collect($thread)->pluck('round')->all());
        $this->assertSame($unitStaff->name, $thread[1]['user_name']);

        $this->actingAs($reviewer)
            ->deleteJson("/api/v1/clients/{$client->id}/supervisor-review/comments/{$reply}")
            ->assertStatus(403);

        $this->actingAs($unitStaff)
            ->deleteJson("/api/v1/clients/{$client->id}/supervisor-review/comments/{$reply}")
            ->assertOk();

        $this->assertSoftDeleted(SupervisorReviewComment::class, ['id' => $reply]);
    }

    public function test_unit_staff_role_can_read_and_comment_but_cannot_sign_off(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Documentation Unit Staff');
        $client = $this->withRuntime($this->client());

        $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/supervisor-review")->assertOk();
        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/supervisor-review/comments", ['body' => 'Uploaded the missing payslip.'])
            ->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/supervisor-review/approve")->assertStatus(403);
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/supervisor-review/send-back", [
            'stage' => 'documentation_unit',
            'reason' => 'Not my call.',
        ])->assertStatus(403);
    }

    public function test_supervisor_role_holds_the_full_review_permission_set(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Supervisor');
        $client = $this->withRuntime($this->client());

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/supervisor-review/approve", ['decision_notes' => 'Approved.'])
            ->assertOk();
    }
}
