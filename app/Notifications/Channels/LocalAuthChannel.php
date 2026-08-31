<?php

namespace App\Notifications\Channels;

use App\Services\Identity\LocalInbox;
use Illuminate\Notifications\Notification;

class LocalAuthChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $mail = $notification->toMail($notifiable);
        app(LocalInbox::class)->store([
            'type' => 'email', 'recipient' => $notifiable->email, 'subject' => $mail->subject, 'url' => $mail->actionUrl,
        ]);
    }
}
