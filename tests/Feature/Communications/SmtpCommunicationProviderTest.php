<?php

namespace Tests\Feature\Communications;

use Illuminate\Support\Facades\Mail;
use Modules\Communications\Models\Message;
use Modules\Communications\Services\Providers\SmtpCommunicationProvider;
use Tests\TestCase;

class SmtpCommunicationProviderTest extends TestCase
{
    public function test_a_successful_send_dispatches_mail_through_the_dynamic_mailer(): void
    {
        Mail::fake();

        $settings = [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'dreamflyaz@gmail.com',
            'password' => 'secret',
            'from_email' => 'dreamflyaz@gmail.com',
            'from_name' => 'Dream Fly',
        ];

        $message = Message::make(['recipient' => 'client@example.com', 'subject' => 'Update', 'body' => '<p>Hello</p>']);
        $result = (new SmtpCommunicationProvider($settings))->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('smtp', $result->provider);
        $this->assertNotNull($result->providerMessageId);
    }
}
