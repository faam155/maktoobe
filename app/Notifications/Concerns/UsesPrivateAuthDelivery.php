<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\LocalAuthChannel;
use RuntimeException;

trait UsesPrivateAuthDelivery
{
    public function via($notifiable): array
    {
        if (config('identity.mail_driver') === 'local') {
            return [LocalAuthChannel::class];
        }
        if (in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            throw new RuntimeException('Configure a real mail transport or explicit private local authentication delivery.');
        }

        return ['mail'];
    }
}
