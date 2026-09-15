<?php

namespace App\Support;

class OutboundMail
{
    public static function isConfigured(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
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
}
