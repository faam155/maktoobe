<?php

namespace Tests\Feature;

use App\Actions\Social\GenerateSocialInsight;
use App\Actions\Social\ImportSocialMetrics;
use App\Actions\Social\RefreshSocialAccount;
use App\Actions\Social\SaveSocialMetric;
use App\Contracts\AiProvider;
use App\Jobs\GenerateSocialInsightJob;
use App\Jobs\SyncSocialAccount;
use App\Models\Event;
use App\Models\SocialContentType;
use App\Models\SocialMediaAccount;
use App\Models\SocialPlatform;
use App\Models\SocialPost;
use App\Models\User;
use App\Queries\Social\SocialAnalyticsQuery;
use App\Services\Social\LinkedInProvider;
use App\Services\Social\SocialFilters;
use App\Services\Social\SocialMath;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SocialMediaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class SocialMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private SocialMediaAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, SocialMediaCatalogSeeder::class]);
        $this->actor = User::factory()->create();
        $this->actor->assignRole(Access::SUPER_ADMINISTRATOR);
        $this->account = SocialMediaAccount::create(['platform_id' => SocialPlatform::first()->id, 'name' => 'Corporate account', 'identifier' => 'test', 'timezone' => 'Asia/Muscat']);
    }

    private function login(?User $u = null): static
    {
        $u ??= $this->actor;

        return $this->actingAs($u)->withSession(['auth.security_version' => $u->security_version, 'auth.confirmed_at' => time()]);
    }

    private function filters(array $extra = []): array
    {
        return SocialFilters::make($this->account, array_merge(['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30'], $extra));
    }

    private function makePost(array $extra = []): SocialPost
    {
        return SocialPost::create($extra + ['social_account_id' => $this->account->id, 'platform_post_id' => uniqid(), 'title' => 'Measured post', 'published_at' => '2026-09-05 08:00:00', 'content_type_id' => SocialContentType::first()->id, 'language' => 'en']);
    }

    private function metric(array $extra = []): array
    {
        return $extra + ['subject' => 'account', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'impressions' => 1000, 'reactions' => 30, 'comments' => 5, 'shares' => 5, 'clicks' => 10, 'followers' => 1100, 'new_followers' => 100];
    }

    public function test_math_distinguishes_missing_zero_and_weighted_targets(): void
    {
        $this->assertNull(SocialMath::percent(10, 0));
        $this->assertSame(0.0, SocialMath::percent(0, 10));
        $this->assertSame(50.0, SocialMath::change(15, 10)['percent']);
        $this->assertNull(SocialMath::change(15, 0)['percent']);
        $this->assertNull(SocialMath::engagements(['reactions' => 2], ['reactions', 'clicks']));
        $this->assertSame(75.0, SocialMath::score(['a' => 50, 'b' => 100], ['a' => 100, 'b' => 100], ['a' => 50, 'b' => 50], ['good' => 75, 'critical' => 0])['value']);
        $this->assertNull(SocialMath::score(['a' => 50], [], ['a' => 100], ['critical' => 0])['value']);
    }

    public function test_metric_corrections_preserve_history_and_overlap_rolls_back(): void
    {
        $action = app(SaveSocialMetric::class);
        $row = $action->handle($this->actor, $this->account, $this->metric());
        $action->handle($this->actor, $this->account, $this->metric(['impressions' => 2000]));
        $this->assertSame(2, $row->fresh()->revision);
        $this->assertDatabaseCount('social_metric_revisions', 1);
        try {
            $action->handle($this->actor, $this->account, $this->metric(['ends_on' => '2026-09-01']));
            $this->fail('Overlap accepted');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('social_account_metrics', 1);
    }

    public function test_analytics_never_adds_account_and_post_measurements(): void
    {
        app(SaveSocialMetric::class)->handle($this->actor, $this->account, $this->metric());
        app(SaveSocialMetric::class)->handle($this->actor, $this->account, $this->metric(['starts_on' => '2026-08-31', 'ends_on' => '2026-08-31', 'followers' => 1000]));
        $post = $this->makePost();
        app(SaveSocialMetric::class)->handle($this->actor, $this->account, $this->metric(['subject' => 'post', 'post_id' => $post->id, 'impressions' => 200]));
        $query = app(SocialAnalyticsQuery::class);
        $data = $query->get($this->actor, $this->account, $this->filters());
        $this->assertSame(1000, $data['metrics']['impressions']);
        $this->assertSame(5.0, $data['metrics']['engagement_rate']);
        $this->assertSame(10.0, $data['metrics']['growth_rate']);
        $filtered = $query->get($this->actor, $this->account, $this->filters(['language' => 'en']));
        $this->assertSame(200, $filtered['metrics']['impressions']);
        $this->assertNull($filtered['metrics']['followers']);
        $this->assertSame(25.0, $filtered['metrics']['engagement_rate']);
    }

    public function test_event_access_precedes_lists_aggregates_and_details(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['linkedin.dashboard.view', 'linkedin.posts.view']);
        $event = Event::factory()->create(['visibility' => 'private', 'organizer_id' => $this->actor->id, 'created_by' => $this->actor->id]);
        $post = $this->makePost(['event_id' => $event->id, 'title' => 'Hidden event post']);
        app(SaveSocialMetric::class)->handle($this->actor, $this->account, $this->metric(['subject' => 'post', 'post_id' => $post->id]));
        $data = app(SocialAnalyticsQuery::class)->get($viewer, $this->account, $this->filters(['language' => 'en']));
        $this->assertSame(0, $data['top']->count());
        $this->assertNull($data['metrics']['impressions']);
        $this->login($viewer)->get(route('social.posts.show', [$this->account, $post]))->assertNotFound();
        $this->get(route('social.dashboard', [$this->account] + $this->filters(['event_id' => $event->id])))->assertNotFound();
    }

    public function test_every_screen_renders_and_mutations_require_permissions(): void
    {
        $post = $this->makePost();
        $this->login();
        foreach (['index' => [], 'accounts' => [], 'dashboard' => [$this->account], 'posts' => [$this->account], 'posts.create' => [$this->account], 'posts.show' => [$this->account, $post], 'posts.edit' => [$this->account, $post], 'metrics' => [$this->account], 'imports' => [$this->account], 'reports' => [$this->account], 'settings' => [$this->account], 'sync' => [$this->account]] as $name => $args) {
            $this->get(route('social.'.$name, $args))->assertOk();
        }
        foreach (['campaign', 'tag', 'target', 'audience'] as $kind) {
            $this->get(route('social.catalog', [$this->account, $kind]))->assertOk();
        }
        $viewer = User::factory()->create();
        $this->login($viewer);
        $this->get(route('social.index'))->assertForbidden();
        $this->post(route('social.metrics.save', $this->account), $this->metric())->assertForbidden();
    }

    public function test_import_preview_is_non_mutating_and_confirmation_is_idempotent(): void
    {
        $file = UploadedFile::fake()->createWithContent('metrics.csv', implode(',', ImportSocialMetrics::HEADER)."\naccount,,2026-09-01,2026-09-30,1000,30,5,5,10,1100,100\n");
        $action = app(ImportSocialMetrics::class);
        $preview = $action->preview($this->actor, $this->account, $file);
        $this->assertSame([], $preview->errors);
        $this->assertDatabaseCount('social_account_metrics', 0);
        $action->confirm($this->actor, $this->account, $preview);
        $action->confirm($this->actor, $this->account, $preview);
        $this->assertDatabaseCount('social_account_metrics', 1);
        $this->assertDatabaseCount('social_sync_logs', 1);
    }

    public function test_import_rejects_invalid_rows_and_cross_account_post(): void
    {
        $file = UploadedFile::fake()->createWithContent('metrics.csv', implode(',', ImportSocialMetrics::HEADER)."\npost,9999,2026-09-01,2026-09-01,1000,30,5,5,10,,\n");
        $preview = app(ImportSocialMetrics::class)->preview($this->actor, $this->account, $file);
        $this->assertNotEmpty($preview->errors);
        $this->assertDatabaseCount('social_post_metrics', 0);
    }

    public function test_date_comparisons_and_partial_months_are_explicit(): void
    {
        $f = $this->filters(['compare' => 'same_period_last_year']);
        $this->assertSame('2025-09-01', $f['comparison_from']);
        app(SaveSocialMetric::class)->handle($this->actor, $this->account, $this->metric());
        $data = app(SocialAnalyticsQuery::class)->get($this->actor, $this->account, $this->filters(['to' => '2026-09-15']));
        $this->assertTrue($data['partial']);
        $this->assertNull($data['metrics']['impressions']);
    }

    public function test_sync_is_queued_and_unconfigured_adapter_fails_safely(): void
    {
        Queue::fake();
        $log = app(RefreshSocialAccount::class)->handle($this->actor, $this->account);
        app(RefreshSocialAccount::class)->handle($this->actor, $this->account);
        $this->assertDatabaseCount('social_sync_logs', 1);
        (new SyncSocialAccount($log->id))->handle(new LinkedInProvider);
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertNull($this->account->fresh()->last_sync_at);
    }

    public function test_ai_rechecks_permissions_before_calling_provider(): void
    {
        Queue::fake();
        $row = app(GenerateSocialInsight::class)->handle($this->actor, $this->account, $this->filters(), ['operation_id' => (string) Str::uuid()]);
        $this->actor->syncRoles([]);
        $provider = \Mockery::mock(AiProvider::class);
        $provider->shouldNotReceive('generate');
        (new GenerateSocialInsightJob($row->id))->handle($provider);
        $this->assertSame('failed', $row->fresh()->status);
    }

    public function test_excel_export_is_real_xlsx_and_does_not_emit_formulas(): void
    {
        $this->account->update(['name' => '=1+1']);
        $this->login();
        $response = $this->get(route('social.export', [$this->account, 'xlsx']));
        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($xml);
        $this->assertStringNotContainsString('<f', $xml);
        $this->assertStringContainsString('=1+1', $xml);
        $zip->close();
        unlink($path);
    }
}
