<?php

// CLI-only fixtures: never registered as HTTP routes or production seed data.
use App\Actions\Identity\SetAccountStatus;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Identity\LocalInbox;
use App\Support\Authorization\Access;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.browser');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('browser') || $app->configurationIsCached()
    || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'maktoobe_browser'
    || config('database.connections.mysql.username') !== 'maktoobe_browser'
    || filled(config('database.connections.mysql.url'))
    || DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account')->db !== 'maktoobe_browser'
    || DB::selectOne('SELECT CURRENT_USER() AS account')->account !== 'maktoobe_browser@127.0.0.1') {
    throw new LogicException('Browser fixtures require the isolated browser database and credentials.');
}
$action = $argv[1] ?? '';
$email = $argv[2] ?? 'member@example.test';
if ($action === 'reset') {
    Artisan::call('migrate:fresh', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true]);
    Storage::disk('local')->deleteDirectory('auth-inbox/browser');
    $user = User::factory()->create(['name' => 'Browser Member', 'username' => 'member', 'email' => 'member@example.test', 'phone_e164' => '+96891111111', 'phone_verified_at' => now(), 'password' => 'BrowserPassword123']);
    $user->assignRole(Access::STANDARD_USER);
    $administrator = User::factory()->create(['name' => 'Browser Administrator', 'username' => 'admin', 'email' => 'admin@example.test', 'password' => 'AdminBrowserPassword123']);
    $administrator->assignRole(Access::SUPER_ADMINISTRATOR);
    echo json_encode(['ready' => true]);
} elseif ($action === 'inbox') {
    $messages = array_values(array_filter(app(LocalInbox::class)->messages(), fn ($message) => ($message['recipient'] ?? null) === $email));
    echo json_encode($messages);
} elseif ($action === 'activate' || $action === 'disable') {
    $user = User::where('email', $email)->firstOrFail();
    app(SetAccountStatus::class)->handle($user, $action === 'activate' ? AccountStatus::Active : AccountStatus::Disabled, 'Browser authentication verification');
    echo json_encode(['updated' => true]);
} else {
    throw new InvalidArgumentException('Unknown browser fixture action.');
}
