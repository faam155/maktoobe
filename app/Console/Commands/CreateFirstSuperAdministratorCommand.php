<?php

namespace App\Console\Commands;

use App\Actions\Administration\CreateFirstSuperAdministrator;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateFirstSuperAdministratorCommand extends Command
{
    protected $signature = 'auth:create-super-admin {email}';

    protected $description = 'Promote one existing registered account as the first Super Administrator';

    public function handle(CreateFirstSuperAdministrator $action): int
    {
        try {
            $user = $action->handle((string) $this->argument('email'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        }
        $this->info("{$user->email} is now the first Super Administrator. Existing sessions were revoked.");

        return self::SUCCESS;
    }
}
