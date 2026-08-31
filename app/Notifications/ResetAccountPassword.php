<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesPrivateAuthDelivery;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetAccountPassword extends ResetPassword
{
    use UsesPrivateAuthDelivery;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('auth.reset_title'))->greeting(__('auth.mail_greeting'))
            ->line(__('auth.reset_intro'))->action(__('auth.reset_title'), $this->resetUrl($notifiable))
            ->line(__('auth.reset_expiry', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('auth.mail_ignore'));
    }
}
