<?php

namespace Tests\Feature;

use App\Actions\Social\GenerateSocialInsight;
use App\Actions\Social\RefreshSocialAccount;
use App\Models\SocialMediaAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Services\Social\SocialFilters;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SocialMediaCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialQueueTest extends TestCase
{
    use DatabaseMigrations;

    public function test_real_commit_rollback_and_workers_for_social_and_ai_queues(): void
    {
        $this->seed([AccessControlSeeder::class, SocialMediaCatalogSeeder::class]);
        config(['queue.default' => 'database', 'ai.provider' => 'local']);
        $actor = User::factory()->create();
        $actor->assignRole(Access::SUPER_ADMINISTRATOR);
        $account = SocialMediaAccount::create(['platform_id' => SocialPlatform::first()->id, 'identifier' => 'queue', 'name' => 'Queue account', 'timezone' => 'Asia/Muscat']);
        DB::beginTransaction();
        app(RefreshSocialAccount::class)->handle($actor, $account);
        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();
        $this->assertSame(0, DB::table('social_sync_logs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $log = app(RefreshSocialAccount::class)->handle($actor, $account);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'social')->count());
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'social', '--once' => true, '--tries' => 1]);
        $this->assertSame('failed', $log->fresh()->status);
        $insight = app(GenerateSocialInsight::class)->handle($actor, $account, SocialFilters::make($account, []), ['operation_id' => (string) Str::uuid()]);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'ai')->count());
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'ai', '--once' => true, '--tries' => 1]);
        $this->assertSame('completed', $insight->fresh()->status);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertStringNotContainsString($insight->fresh()->content, DB::table('social_ai_insights')->where('id', $insight->id)->value('content'));
    }
}
