<?php

namespace App\Contracts;

interface SmsGateway
{
    public function sendOtp(string $phone, string $code): void;
}
