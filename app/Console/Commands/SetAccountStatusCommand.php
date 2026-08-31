<?php

namespace App\Console\Commands;

use App\Actions\Identity\SetAccountStatus;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\Identity\Identifiers;
use Illuminate\Console\Command;

class SetAccountStatusCommand extends Command
{
    protected $signature = 'auth:account-status {email} {status} {--reason=}';

    protected $description = 'Set account approval/status with a required audit reason; trusted operators only';

    public function handle(SetAccountStatus $action): int
    {
        $status = AccountStatus::tryFrom($this->argument('status'));
        $reason = trim((string) $this->option('reason'));
        if (! $status || strlen($reason) < 8 || strlen($reason) > 200) {
            $this->error('Use pending, active or disabled and a non-sensitive reason of 8–200 characters.');

            return self::FAILURE;
        }
        $user = User::where('email', Identifiers::canonical($this->argument('email')))->first();
        if (! $user) {
            $this->error('Account not found.');

            return self::FAILURE;
        }
        $action->handle($user, $status, $reason);
        $this->info('Account status updated. Existing credentials and sessions were revoked.');

        return self::SUCCESS;
    }
}
