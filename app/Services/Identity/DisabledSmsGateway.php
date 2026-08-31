<?php

namespace App\Services\Identity;

use App\Contracts\SmsGateway;
use RuntimeException;

class DisabledSmsGateway implements SmsGateway
{
    public function sendOtp(string $phone, string $code): void
    {
        throw new RuntimeException('SMS delivery is not configured.');
    }
}
