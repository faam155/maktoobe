<?php

namespace App\Services\Identity;

use App\Contracts\SmsGateway;

class LocalSmsGateway implements SmsGateway
{
    public function sendOtp(string $phone, string $code): void
    {
        app(LocalInbox::class)->store(['type' => 'sms', 'recipient' => $phone, 'code' => $code]);
    }
}
