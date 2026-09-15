<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class GuestPasswordResetNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        if ((bool) config('auth.passwords.users.reveal_link', false)) {
            session()->flash(
                'password_reset_url',
                route('password.reset', [
                    'token' => $this->token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false)
            );
        }

        return (new MailMessage)
            ->subject('Reset your '.config('resort.short_name').' password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password for your '.config('resort.short_name').' account.')
            ->action('Choose a new password', $url)
            ->line("This link expires in {$minutes} minutes.")
            ->line('If you did not ask for this, you can ignore this email.')
            ->salutation('See you at the resort,'.PHP_EOL.config('resort.short_name'));
    }
}
