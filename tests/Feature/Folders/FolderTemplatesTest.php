<?php

namespace Tests\Feature\Folders;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use Tests\TestCase;

class FolderTemplatesTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(string $reference = 'DF-150-2026'): Client
    {
        return Client::create([
            'reference_no' => $reference,
            'full_name' => 'Folder Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'admin_summary',
            'status' => 'active',
        ]);
    }

    public function test_general_folder_can_be_propagated_to_existing_clients_without_duplicates(): void
    {
        $user = $this->staff(['folders.create', 'folders.view']);
        $client = $this->client();

        $response = $this->actingAs($user)->postJson('/api/v1/folders', [
            'name' => 'Embassy Copies',
            'is_general' => true,
            'auto_create_for_clients' => true,
            'propagate_existing' => true,
        ]);

        $response->assertCreated();
        $templateId = $response->json('data.id');

        $this->assertDatabaseHas('folders', [
            'id' => $templateId,
            'name' => 'Embassy Copies',
            'scope' => 'global',
            'is_general' => true,
            'auto_create_for_clients' => true,
        ]);
        $this->assertDatabaseHas('folders', [
            'template_id' => $templateId,
            'client_id' => $client->id,
            'name' => 'Embassy Copies',
            'scope' => 'client',
        ]);

        $this->actingAs($user)->postJson("/api/v1/folders/{$templateId}/propagate")->assertOk();

        $this->assertSame(1, Folder::where('template_id', $templateId)->where('client_id', $client->id)->count());
    }

    public function test_future_client_folder_tree_includes_auto_general_templates(): void
    {
        $user = $this->staff('folders.create');
        $template = Folder::create([
            'name' => 'Embassy Copies',
            'slug' => 'embassy-copies',
            'scope' => 'global',
            'is_general' => true,
            'auto_create_for_clients' => true,
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $client = $this->client('DF-151-2026');

        app(FolderService::class)->createClientFolderTree($client, $user->id);

        $this->assertDatabaseHas('folders', [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'name' => 'Embassy Copies',
            'scope' => 'client',
        ]);
        $this->assertDatabaseHas('folders', [
            'client_id' => $client->id,
            'name' => 'Applicant Documents',
            'scope' => 'client',
        ]);
    }

    public function test_client_folder_tree_nests_under_a_country_folder_under_the_clients_root(): void
    {
        $user = $this->staff('folders.create');
        $client = $this->client('DF-152-2026');

        app(FolderService::class)->createClientFolderTree($client, $user->id);

        $clientsRoot = Folder::where('name', 'Clients')->whereNull('parent_id')->first();
        $this->assertNotNull($clientsRoot);

        $countryFolder = Folder::where('parent_id', $clientsRoot->id)->where('name', 'United Kingdom')->first();
        $this->assertNotNull($countryFolder);

        $this->assertDatabaseHas('folders', [
            'client_id' => $client->id,
            'parent_id' => $countryFolder->id,
        ]);
    }

    public function test_lead_folder_tree_builds_under_a_common_users_root_nested_by_country(): void
    {
        $user = $this->staff('folders.create');
        $lead = CommonUser::create([
            'full_name' => 'Folder Lead',
            'country' => 'Australia',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        app(FolderService::class)->createLeadFolderTree($lead, $user->id);

        $leadsRoot = Folder::where('name', 'Common Users')->whereNull('parent_id')->first();
        $this->assertNotNull($leadsRoot);

        $countryFolder = Folder::where('parent_id', $leadsRoot->id)->where('name', 'Australia')->first();
        $this->assertNotNull($countryFolder);

        $ownFolder = Folder::where('common_user_id', $lead->id)->where('parent_id', $countryFolder->id)->first();
        $this->assertNotNull($ownFolder);

        $this->assertDatabaseHas('folders', [
            'common_user_id' => $lead->id,
            'parent_id' => $ownFolder->id,
            'name' => 'Applicant Documents',
        ]);
    }

    public function test_two_clients_with_the_same_destination_country_share_one_country_folder(): void
    {
        $user = $this->staff('folders.create');
        $service = app(FolderService::class);

        $clientA = $this->client('DF-153-2026');
        $clientB = $this->client('DF-154-2026');

        $service->createClientFolderTree($clientA, $user->id);
        $service->createClientFolderTree($clientB, $user->id);

        $countryFolders = Folder::where('name', 'United Kingdom')->whereNull('client_id')->whereNull('common_user_id')->get();
        $this->assertCount(1, $countryFolders);
    }

    public function test_propagating_a_general_folder_also_reaches_not_yet_converted_leads(): void
    {
        $user = $this->staff(['folders.create', 'folders.view']);
        $lead = CommonUser::create([
            'full_name' => 'Propagate Lead',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/folders', [
            'name' => 'Sponsor Letters',
            'is_general' => true,
            'auto_create_for_clients' => true,
            'propagate_existing' => true,
        ]);

        $response->assertCreated();
        $templateId = $response->json('data.id');

        $this->assertDatabaseHas('folders', [
            'template_id' => $templateId,
            'common_user_id' => $lead->id,
            'name' => 'Sponsor Letters',
            'scope' => 'lead',
        ]);
    }
}
