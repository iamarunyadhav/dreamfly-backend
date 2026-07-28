<?php

namespace Tests\Feature\Communications;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Communications\Models\AlertDispatch;
use Modules\Communications\Models\AlertTemplate;
use Modules\Communications\Models\Message;
use Modules\Communications\Models\MessageTemplate;
use Modules\Communications\Services\AlertDispatcher;
use Modules\System\Models\Notification;
use Tests\TestCase;

class AlertDispatcherTest extends TestCase
{
    private function dispatcher(): AlertDispatcher
    {
        return app(AlertDispatcher::class);
    }

    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-500-2026',
            'full_name' => 'Alert Target',
            'email' => 'alert.target@example.com',
            'phone' => '0770000000',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
        ]);
    }

    private function template(array $overrides = []): AlertTemplate
    {
        return AlertTemplate::create([
            'name' => 'Overdue escalation',
            'trigger' => 'overdue',
            'channels' => ['internal'],
            'recipient_rules' => ['roles' => ['Supervisor']],
            'delay_minutes' => 0,
            'is_enabled' => true,
            ...$overrides,
        ]);
    }

    public function test_trigger_queues_a_dispatch_for_each_enabled_matching_template(): void
    {
        $this->template();
        $this->template(['name' => 'Disabled one', 'is_enabled' => false]);
        $this->template(['name' => 'Other trigger', 'trigger' => 'case_closed']);

        $queued = $this->dispatcher()->trigger('overdue', ['client_reference' => 'DF-500-2026']);

        $this->assertSame(1, $queued);
        $this->assertSame(1, AlertDispatch::where('trigger', 'overdue')->count());
    }

    public function test_conditions_filter_which_templates_fire(): void
    {
        $this->template(['name' => 'Visit only', 'conditions' => ['service_category' => 'visit_visa']]);
        $this->template(['name' => 'Student only', 'conditions' => ['service_category' => 'student_visa']]);
        $this->template(['name' => 'High or urgent', 'conditions' => ['priority' => ['high', 'urgent']]]);

        $queued = $this->dispatcher()->trigger('overdue', [
            'service_category' => 'visit_visa',
            'priority' => 'urgent',
        ]);

        $this->assertSame(2, $queued);
    }

    public function test_a_delay_holds_the_dispatch_until_it_is_due(): void
    {
        $this->template(['delay_minutes' => 30]);
        $this->dispatcher()->trigger('overdue', []);

        $this->assertSame(['sent' => 0, 'failed' => 0, 'skipped' => 0], $this->dispatcher()->flushDue());
        $this->assertSame('pending', AlertDispatch::first()->status);

        $this->travel(31)->minutes();

        $this->assertSame(1, $this->dispatcher()->flushDue()['sent']);
        $this->assertSame('sent', AlertDispatch::first()->fresh()->status);
    }

    public function test_the_same_event_only_queues_once(): void
    {
        $this->template();

        $this->dispatcher()->trigger('overdue', [], 'task-7-overdue');
        $this->dispatcher()->trigger('overdue', [], 'task-7-overdue');

        $this->assertSame(1, AlertDispatch::count());
    }

    public function test_internal_channel_creates_role_and_user_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = $this->client();
        $this->template(['recipient_rules' => ['roles' => ['Supervisor'], 'users' => [$user->id]]]);

        $this->dispatcher()->trigger('overdue', ['client_id' => $client->id, 'client_reference' => $client->reference_no]);
        $this->dispatcher()->flushDue();

        $this->assertDatabaseHas('notifications', ['role' => 'Supervisor', 'type' => 'alert.overdue']);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'alert.overdue']);
        $this->assertSame(2, Notification::where('type', 'alert.overdue')->count());
        $this->assertSame(2, AlertDispatch::first()->fresh()->recipients_notified);
    }

    public function test_external_channel_sends_to_the_client_and_records_the_message(): void
    {
        $client = $this->client();
        $this->template([
            'channels' => ['email'],
            'recipient_rules' => ['client' => true],
        ]);

        $this->dispatcher()->trigger('overdue', ['client_id' => $client->id, 'client_reference' => $client->reference_no]);
        $this->dispatcher()->flushDue();

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertSame('email', $message->channel);
        $this->assertSame($client->email, $message->recipient);
        $this->assertSame($client->id, $message->client_id);
    }

    public function test_message_template_placeholders_are_filled_from_the_context(): void
    {
        $client = $this->client();
        $messageTemplate = MessageTemplate::create([
            'name' => 'Overdue notice',
            'channel' => 'email',
            'subject' => 'Action needed on {{client_reference}}',
            'body' => 'Task "{{task_title}}" for {{client_name}} is overdue.',
            'is_active' => true,
        ]);
        $this->template([
            'channels' => ['email'],
            'recipient_rules' => ['client' => true],
            'message_template_id' => $messageTemplate->id,
        ]);

        $this->dispatcher()->trigger('overdue', [
            'client_id' => $client->id,
            'client_reference' => $client->reference_no,
            'client_name' => $client->full_name,
            'task_title' => 'Police clearance',
        ]);
        $this->dispatcher()->flushDue();

        $message = Message::first();
        $this->assertSame('Action needed on DF-500-2026', $message->subject);
        $this->assertSame('Task "Police clearance" for Alert Target is overdue.', $message->body);
    }

    public function test_a_dispatch_with_no_resolvable_recipient_is_skipped_not_failed(): void
    {
        $this->template(['channels' => ['email'], 'recipient_rules' => []]);

        $this->dispatcher()->trigger('overdue', []);

        $this->assertSame(1, $this->dispatcher()->flushDue()['skipped']);
        $this->assertSame('skipped', AlertDispatch::first()->fresh()->status);
    }

    public function test_recording_a_payment_raises_the_payment_received_trigger(): void
    {
        $client = $this->client();
        $this->template(['trigger' => 'payment_received']);

        app(\Modules\Payments\Services\PaymentService::class)->create([
            'client_id' => $client->id,
            'amount' => 25000,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('alert_dispatches', ['trigger' => 'payment_received', 'client_id' => $client->id]);
    }
}
