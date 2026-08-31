<?php

namespace App\Console\Commands;

use App\Services\Identity\LocalInbox;
use Illuminate\Console\Command;

class LocalAuthInboxCommand extends Command
{
    protected $signature = 'auth:inbox {--recipient=} {--latest}';

    protected $description = 'Read private local authentication deliveries from a trusted local terminal';

    public function handle(LocalInbox $inbox): int
    {
        if (! app()->environment(['local', 'browser', 'testing'])) {
            $this->error('Local inbox is disabled.');

            return self::FAILURE;
        }
        $messages = collect($inbox->messages())->when($this->option('recipient'), fn ($items) => $items->where('recipient', $this->option('recipient')))->values();
        $this->line(json_encode($this->option('latest') ? $messages->last() : $messages->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
