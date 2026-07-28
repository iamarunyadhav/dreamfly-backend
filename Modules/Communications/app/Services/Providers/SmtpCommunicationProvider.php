<?php

namespace Modules\Communications\Services\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Communications\Models\Message;
use Throwable;

/**
 * Sends email through Laravel's own SMTP transport, configured at send-time
 * from the stored channel settings rather than a custom mail client.
 */
class SmtpCommunicationProvider implements CommunicationProviderInterface
{
    private const MAILER_NAME = 'dynamic-smtp';

    public function __construct(private array $settings)
    {
    }

    public function send(Message $message): ProviderResult
    {
        try {
            config(['mail.mailers.'.self::MAILER_NAME => [
                'transport' => 'smtp',
                'host' => $this->settings['host'],
                'port' => $this->settings['port'],
                'encryption' => $this->settings['encryption'] === 'none' ? null : $this->settings['encryption'],
                'username' => $this->settings['username'],
                'password' => $this->settings['password'],
            ]]);

            $fromEmail = $this->settings['from_email'];
            $fromName = $this->settings['from_name'];
            $recipient = $message->recipient;
            $subject = $message->subject ?? 'Dream Fly Visa Consultancy';

            Mail::mailer(self::MAILER_NAME)->html($message->body, function ($mail) use ($recipient, $subject, $fromEmail, $fromName) {
                $mail->to($recipient)->subject($subject);
                if ($fromEmail) {
                    $mail->from($fromEmail, $fromName);
                }
            });

            // SMTP itself has no provider message id until the receiving MTA
            // assigns one - there's no delivery webhook for raw SMTP either,
            // so this id only exists to satisfy the ProviderResult contract.
            return new ProviderResult(success: true, provider: 'smtp', providerMessageId: 'smtp_'.Str::uuid()->toString());
        } catch (Throwable $e) {
            return new ProviderResult(success: false, provider: 'smtp', failureReason: $e->getMessage());
        }
    }
}
