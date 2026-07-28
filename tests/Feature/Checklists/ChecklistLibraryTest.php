<?php

namespace Tests\Feature\Checklists;

use App\Models\User;
use Modules\Checklists\Models\ChecklistCategory;
use Modules\Checklists\Models\ChecklistTemplate;
use Tests\TestCase;

class ChecklistLibraryTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_create_defaults_to_draft_and_owner_library(): void
    {
        $user = $this->user(['checklists.create']);

        $response = $this->actingAs($user)->postJson('/api/v1/checklists', [
            'title' => 'Bank statement',
            'owner' => 'applicant',
            'category' => 'client_documents',
            'is_required' => true,
            'document_required' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.owner', 'applicant');
        $response->assertJsonPath('data.version', 1);
    }

    public function test_publish_snapshots_a_version_and_edit_returns_to_draft(): void
    {
        $user = $this->user(['checklists.create', 'checklists.edit', 'checklists.view']);
        $item = ChecklistTemplate::create([
            'title' => 'Passport', 'owner' => 'applicant', 'category' => 'client_documents',
            'is_required' => true, 'document_required' => true, 'status' => 'draft', 'version' => 1,
        ]);

        $publish = $this->actingAs($user)->postJson("/api/v1/checklists/{$item->id}/publish");
        $publish->assertOk();
        $publish->assertJsonPath('data.status', 'published');
        $publish->assertJsonPath('data.version', 1);

        $versions = $this->actingAs($user)->getJson("/api/v1/checklists/{$item->id}/versions");
        $versions->assertOk();
        $this->assertCount(1, $versions->json('data'));

        // Editing a published item sends it back to draft.
        $this->actingAs($user)->putJson("/api/v1/checklists/{$item->id}", ['title' => 'Passport (bio page)'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        // Re-publishing creates version 2.
        $this->actingAs($user)->postJson("/api/v1/checklists/{$item->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.version', 2);
    }

    public function test_restore_brings_back_a_previous_version_as_draft(): void
    {
        $user = $this->user(['checklists.create', 'checklists.edit', 'checklists.view']);
        $item = ChecklistTemplate::create([
            'title' => 'Original title', 'owner' => 'applicant', 'category' => 'client_documents',
            'is_required' => true, 'document_required' => false, 'status' => 'draft', 'version' => 1,
        ]);
        $this->actingAs($user)->postJson("/api/v1/checklists/{$item->id}/publish")->assertOk(); // v1

        $this->actingAs($user)->putJson("/api/v1/checklists/{$item->id}", ['title' => 'Changed title'])->assertOk();
        $this->actingAs($user)->postJson("/api/v1/checklists/{$item->id}/publish")->assertOk(); // v2

        $restore = $this->actingAs($user)->postJson("/api/v1/checklists/{$item->id}/restore", ['version' => 1]);
        $restore->assertOk();
        $restore->assertJsonPath('data.status', 'draft');
        $this->assertSame('Original title', $item->refresh()->title);
    }

    public function test_owner_filter_scopes_the_library(): void
    {
        $user = $this->user(['checklists.view']);
        ChecklistTemplate::create(['title' => 'A', 'owner' => 'applicant', 'category' => 'x', 'status' => 'published', 'version' => 1]);
        ChecklistTemplate::create(['title' => 'B', 'owner' => 'inviter', 'category' => 'x', 'status' => 'published', 'version' => 1]);

        $response = $this->actingAs($user)->getJson('/api/v1/checklists?owner=inviter');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('inviter', $data[0]['owner']);
    }

    public function test_category_crud_and_uniqueness(): void
    {
        $user = $this->user(['checklists.view', 'checklists.create', 'checklists.delete']);

        // Defaults were seeded by the migration.
        $this->actingAs($user)->getJson('/api/v1/checklist-categories')->assertOk();

        $created = $this->actingAs($user)->postJson('/api/v1/checklist-categories', [
            'name' => 'medical', 'owner' => 'applicant', 'order' => 1,
        ]);
        $created->assertCreated();

        // Duplicate name+owner rejected.
        $this->actingAs($user)->postJson('/api/v1/checklist-categories', [
            'name' => 'medical', 'owner' => 'applicant',
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $category = ChecklistCategory::where('name', 'medical')->where('owner', 'applicant')->first();
        $this->actingAs($user)->deleteJson("/api/v1/checklist-categories/{$category->id}")->assertOk();
    }
}
