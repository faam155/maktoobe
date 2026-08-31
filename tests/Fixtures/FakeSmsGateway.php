<?php

namespace Tests\Fixtures;

use App\Contracts\SmsGateway;

class FakeSmsGateway implements SmsGateway
{
    public array $sent = [];

    public function sendOtp(string $phone, string $code): void
    {
        $this->sent[] = ['phone' => $phone, 'code' => $code];
    }
}
