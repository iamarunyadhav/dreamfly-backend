<?php

namespace Tests\Feature\Communications;

use App\Models\User;
use Modules\Communications\Models\MessageTemplate;
use Tests\TestCase;

class AlertTemplatesTest extends TestCase
{
    public function test_alert_template_can_be_created_and_listed(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['communications.view', 'communications.create']);
        $template = MessageTemplate::create([
            'name' => 'Deadline reminder',
            'channel' => 'whatsapp',
            'body' => 'Task {{task.title}} is due soon.',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/communications/alerts', [
            'message_template_id' => $template->id,
            'name' => 'Documentation deadline reminder',
            'trigger' => 'deadline_near',
            'conditions' => ['priority' => 'high'],
            'recipient_rules' => ['roles' => ['Supervisor']],
            'channels' => ['internal', 'whatsapp'],
            'delay_minutes' => 15,
            'repeat_rule' => 'every 24 hours until completed',
            'escalation_rule' => 'notify manager after 48 hours',
            'is_enabled' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Documentation deadline reminder');
        $response->assertJsonPath('data.message_template_name', 'Deadline reminder');

        $this->actingAs($user)->getJson('/api/v1/communications/alerts')
            ->assertOk()
            ->assertJsonFragment(['trigger' => 'deadline_near']);
    }

    public function test_alert_template_requires_valid_channel(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('communications.create');

        $this->actingAs($user)->postJson('/api/v1/communications/alerts', [
            'name' => 'Bad alert',
            'trigger' => 'deadline_near',
            'channels' => ['telegram'],
        ])->assertStatus(422);
    }
}
