<?php

return [
    'mail_driver' => env('AUTH_MAIL_DRIVER', 'mail'),
    'sms_driver' => env('SMS_DRIVER', 'disabled'),
    'otp_lifetime' => 300,
    'otp_attempts' => 5,
    'otp_cooldown' => 60,
    'recent_auth_seconds' => 900,
    // Every sign-in method traverses these checks before a session is granted.
    // Future MFA middleware must challenge here, not only in the password form.
    'additional_sign_in_checks' => [],
];
