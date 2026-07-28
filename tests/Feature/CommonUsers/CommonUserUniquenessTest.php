<?php

namespace Tests\Feature\CommonUsers;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Folders\Models\Folder;
use Tests\TestCase;

class CommonUserUniquenessTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function lead(array $overrides = []): CommonUser
    {
        return CommonUser::create([
            'full_name' => 'Existing Lead',
            'phone' => '0771111111',
            'email' => 'existing.lead@example.com',
            'nic' => '199911111111',
            'passport_no' => 'N1111111',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'status' => 'unpaid',
            ...$overrides,
        ]);
    }

    private function client(array $overrides = []): Client
    {
        return Client::create([
            'reference_no' => 'DF-900-2026',
            'full_name' => 'Existing Client',
            'phone' => '0772222222',
            'email' => 'existing.client@example.com',
            'nic' => '199922222222',
            'passport_no' => 'N2222222',
            'service_category' => 'visit_visa',
            'current_stage' => 'admin_summary',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_creating_a_lead_with_a_duplicate_phone_is_rejected(): void
    {
        $this->lead();
        $user = $this->staff('common-users.create');

        $response = $this->actingAs($user)->postJson('/api/v1/common-users', [
            'full_name' => 'New Lead',
            'phone' => '0771111111',
            'agreement_amount' => 50000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_creating_a_lead_cannot_reuse_an_existing_clients_passport(): void
    {
        $this->client();
        $user = $this->staff('common-users.create');

        $response = $this->actingAs($user)->postJson('/api/v1/common-users', [
            'full_name' => 'New Lead',
            'passport_no' => 'N2222222',
            'agreement_amount' => 50000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['passport_no']);
    }

    public function test_creating_a_lead_with_a_duplicate_email_or_nic_is_rejected(): void
    {
        $this->lead();
        $user = $this->staff('common-users.create');

        $emailResponse = $this->actingAs($user)->postJson('/api/v1/common-users', [
            'full_name' => 'New Lead',
            'email' => 'existing.lead@example.com',
            'agreement_amount' => 50000,
        ]);
        $emailResponse->assertJsonValidationErrors(['email']);

        $nicResponse = $this->actingAs($user)->postJson('/api/v1/common-users', [
            'full_name' => 'Another Lead',
            'nic' => '199911111111',
            'agreement_amount' => 50000,
        ]);
        $nicResponse->assertJsonValidationErrors(['nic']);
    }

    public function test_updating_a_lead_does_not_collide_with_itself(): void
    {
        $lead = $this->lead();
        $user = $this->staff('common-users.edit');

        $response = $this->actingAs($user)->putJson("/api/v1/common-users/{$lead->id}", [
            'full_name' => 'Existing Lead',
            'phone' => '0771111111',
            'agreement_amount' => 100000,
        ]);

        $response->assertOk();
    }

    public function test_updating_a_converted_leads_own_client_does_not_false_positive(): void
    {
        $lead = $this->lead(['status' => 'converted']);
        $pairedClient = $this->client([
            'reference_no' => 'DF-901-2026',
            'common_user_id' => $lead->id,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'nic' => $lead->nic,
            'passport_no' => $lead->passport_no,
        ]);
        $user = $this->staff('common-users.edit');

        // Editing the lead while keeping the same phone/email/NIC/passport that
        // its own converted client record carries must not be treated as a
        // collision with "another" record.
        $response = $this->actingAs($user)->putJson("/api/v1/common-users/{$lead->id}", [
            'full_name' => 'Existing Lead',
            'phone' => $lead->phone,
            'email' => $lead->email,
            'nic' => $lead->nic,
            'passport_no' => $lead->passport_no,
            'agreement_amount' => 100000,
        ]);

        $response->assertOk();
        $this->assertNotNull($pairedClient->fresh());
    }

    public function test_updating_a_lead_to_collide_with_an_unrelated_client_is_rejected(): void
    {
        $lead = $this->lead();
        $this->client();
        $user = $this->staff('common-users.edit');

        $response = $this->actingAs($user)->putJson("/api/v1/common-users/{$lead->id}", [
            'full_name' => 'Existing Lead',
            'phone' => '0772222222',
            'agreement_amount' => 100000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_a_soft_deleted_leads_phone_can_be_reused(): void
    {
        $lead = $this->lead();
        $lead->delete();
        $user = $this->staff('common-users.create');

        $response = $this->actingAs($user)->postJson('/api/v1/common-users', [
            'full_name' => 'New Lead',
            'phone' => '0771111111',
            'agreement_amount' => 50000,
        ]);

        $response->assertCreated();
    }

    public function test_creating_a_lead_builds_its_folder_tree_under_common_users(): void
    {
        $user = $this->staff('common-users.create');

        $response = $this->actingAs($user)->postJson('/api/v1/common-users', [
            'full_name' => 'Tree Lead',
            'country' => 'United Kingdom',
            'agreement_amount' => 50000,
        ]);

        $response->assertCreated();
        $leadId = $response->json('data.id');

        $leadsRoot = Folder::where('name', 'Common Users')->whereNull('parent_id')->first();
        $this->assertNotNull($leadsRoot);

        $countryFolder = Folder::where('parent_id', $leadsRoot->id)->where('name', 'United Kingdom')->first();
        $this->assertNotNull($countryFolder);

        $this->assertDatabaseHas('folders', [
            'common_user_id' => $leadId,
            'parent_id' => $countryFolder->id,
        ]);
        $this->assertDatabaseHas('folders', [
            'common_user_id' => $leadId,
            'name' => 'Applicant Documents',
        ]);
    }
}
