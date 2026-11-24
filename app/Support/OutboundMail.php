<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class OutboundMail
{
    public static function isConfigured(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        if (config('preview.enabled')) {
            return false;
        }

        return match (config('mail.default')) {
            'smtp' => filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password'))
                && filled(config('mail.mailers.smtp.host'))
                && ! in_array(config('mail.mailers.smtp.host'), ['127.0.0.1', 'localhost'], true),
            'log' => app()->environment('local'),
            'resend' => filled(config('services.resend.key')),
            'ses', 'postmark', 'mailgun' => true,
            default => false,
        };
    }

    /**
     * Run an outbound-mail side effect without letting a transport, rendering,
     * template, or queue failure escape.
     *
     * Email is a secondary copy of something the database already committed.
     * A mailer outage must leave the booking, payment, or stay recorded and
     * return a normal response instead of an HTTP 500.
     *
     * @param  callable(): void  $send
     * @param  array<string, mixed>  $context
     */
    public static function safely(string $failureMessage, callable $send, array $context = []): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::warning($failureMessage, $context + [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
