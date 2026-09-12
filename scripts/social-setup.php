<?php

use Database\Seeders\SocialMediaCatalogSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Local, additive rollout only. Never resets application data.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$identity = DB::selectOne('SELECT DATABASE() db, CURRENT_USER() account');
if (! $app->environment('local') || $app->configurationIsCached() || config('database.default') !== 'mysql' ||
    $identity->db !== 'maktoobe' || $identity->account !== 'maktoobe_app@127.0.0.1' ||
    config('database.connections.mysql.host') !== '127.0.0.1' || (int) config('database.connections.mysql.port') !== 3307) {
    throw new LogicException('Expected the isolated local maktoobe database/user on 127.0.0.1:3307.');
}
echo json_encode(['database' => $identity->db, 'account' => $identity->account, 'port' => 3307]).PHP_EOL;
Artisan::call('migrate', ['--force' => true]);
echo Artisan::output();
Artisan::call('db:seed', ['--class' => SocialMediaCatalogSeeder::class, '--force' => true]);
echo Artisan::output();
