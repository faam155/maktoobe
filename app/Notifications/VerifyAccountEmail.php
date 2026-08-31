<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesPrivateAuthDelivery;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyAccountEmail extends VerifyEmail
{
    use UsesPrivateAuthDelivery;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('auth.verify_title'))->greeting(__('auth.mail_greeting'))
            ->line(__('auth.verify_intro'))->action(__('auth.verify_action'), $this->verificationUrl($notifiable))
            ->line(__('auth.mail_ignore'));
    }
}
