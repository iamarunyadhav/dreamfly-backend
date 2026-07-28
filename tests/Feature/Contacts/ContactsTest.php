<?php

namespace Tests\Feature\Contacts;

use App\Models\User;
use Modules\Contacts\Models\Contact;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    private function userWithout(): User
    {
        return User::factory()->create(['status' => 'active']);
    }

    public function test_user_without_permission_cannot_create_contact(): void
    {
        $user = $this->userWithout();

        $response = $this->actingAs($user)->postJson('/api/v1/contacts', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_user_with_permission_can_create_contact_and_activity_is_logged(): void
    {
        $user = $this->userWithout();
        $user->givePermissionTo('contacts.create');

        $response = $this->actingAs($user)->postJson('/api/v1/contacts', [
            'name' => 'Jane Doe',
            'type' => 'client',
            'email' => 'jane@example.com',
            'phone' => '0400000000',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Jane Doe');
        $response->assertJsonPath('data.type', 'client');

        $this->assertDatabaseHas('contacts', ['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->assertSame(
            1,
            Activity::where('subject_type', Contact::class)->count()
        );
    }

    public function test_user_without_permission_cannot_list_contacts(): void
    {
        $user = $this->userWithout();

        $response = $this->actingAs($user)->getJson('/api/v1/contacts');

        $response->assertStatus(403);
    }

    public function test_user_with_view_permission_can_list_and_search_contacts(): void
    {
        $user = $this->userWithout();
        $user->givePermissionTo('contacts.view');

        Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);
        Contact::create(['name' => 'Bob Beta', 'type' => 'supplier']);

        $response = $this->actingAs($user)->getJson('/api/v1/contacts');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        $searchResponse = $this->actingAs($user)->getJson('/api/v1/contacts?search=Alice');
        $searchResponse->assertOk();
        $searchData = $searchResponse->json('data');
        $this->assertCount(1, $searchData);
        $this->assertSame('Alice Alpha', $searchData[0]['name']);
    }

    public function test_user_with_view_permission_can_show_a_contact(): void
    {
        $user = $this->userWithout();
        $user->givePermissionTo('contacts.view');

        $contact = Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);

        $response = $this->actingAs($user)->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $contact->id);
        $response->assertJsonPath('data.name', 'Alice Alpha');
    }

    public function test_user_without_permission_cannot_show_contact(): void
    {
        $user = $this->userWithout();
        $contact = Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);

        $response = $this->actingAs($user)->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertStatus(403);
    }

    public function test_user_with_edit_permission_can_update_contact(): void
    {
        $user = $this->userWithout();
        $user->givePermissionTo('contacts.edit');

        $contact = Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);

        $response = $this->actingAs($user)->putJson("/api/v1/contacts/{$contact->id}", [
            'name' => 'Alice Updated',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Alice Updated');
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Alice Updated']);
    }

    public function test_user_without_permission_cannot_update_contact(): void
    {
        $user = $this->userWithout();
        $contact = Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);

        $response = $this->actingAs($user)->putJson("/api/v1/contacts/{$contact->id}", [
            'name' => 'Alice Updated',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Alice Alpha']);
    }

    public function test_user_with_delete_permission_can_soft_delete_contact(): void
    {
        $user = $this->userWithout();
        $user->givePermissionTo('contacts.delete');

        $contact = Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);

        $response = $this->actingAs($user)->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk();
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_user_without_permission_cannot_delete_contact(): void
    {
        $user = $this->userWithout();
        $contact = Contact::create(['name' => 'Alice Alpha', 'type' => 'client']);

        $response = $this->actingAs($user)->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'deleted_at' => null]);
    }

    public function test_super_admin_can_perform_full_contact_crud(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $create = $this->actingAs($admin)->postJson('/api/v1/contacts', [
            'name' => 'Super Contact',
            'type' => 'general',
        ]);
        $create->assertStatus(201);
        $contactId = $create->json('data.id');

        $this->actingAs($admin)->getJson('/api/v1/contacts')->assertOk();
        $this->actingAs($admin)->getJson("/api/v1/contacts/{$contactId}")->assertOk();

        $this->actingAs($admin)->putJson("/api/v1/contacts/{$contactId}", [
            'name' => 'Super Contact Updated',
        ])->assertOk();

        $this->actingAs($admin)->deleteJson("/api/v1/contacts/{$contactId}")->assertOk();

        $this->assertSoftDeleted('contacts', ['id' => $contactId]);
    }
}
