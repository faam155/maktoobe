<?php

namespace Tests\Feature;

use App\Actions\Social\ImportSocialMetrics;
use App\Actions\Social\ManageSocialCatalog;
use App\Models\SocialContentType;
use App\Models\SocialMediaAccount;
use App\Models\SocialPost;
use App\Models\SocialReport;
use App\Models\SocialTarget;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\SocialMediaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class SocialManagementTest extends TestCase
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
        $this->account = app(ManageSocialCatalog::class)->account($this->actor, ['name' => 'Corporate', 'identifier' => 'corporate', 'timezone' => 'Asia/Muscat', 'active' => 1]);
        $this->actingAs($this->actor)->withSession(['auth.security_version' => $this->actor->security_version, 'auth.confirmed_at' => time()]);
    }

    public function test_post_create_edit_archive_and_delete_through_http_with_stale_revision_rejection(): void
    {
        $v = ['title' => 'Initial title', 'platform_post_id' => 'p1', 'published_at' => '2026-09-05T12:00', 'content_type_id' => SocialContentType::first()->id, 'language' => 'en', 'status' => 'published'];
        $this->post(route('social.posts.store', $this->account), $v)->assertRedirect();
        $p = SocialPost::firstOrFail();
        $this->assertSame('2026-09-05 08:00:00', $p->published_at->format('Y-m-d H:i:s'));
        $this->put(route('social.posts.update', [$this->account, $p]), array_merge($v, ['title' => 'Edited title', 'revision' => '1']))->assertRedirect();
        $this->assertSame('Edited title', $p->fresh()->title);
        $this->assertSame(2, $p->fresh()->revision);
        $this->put(route('social.posts.update', [$this->account, $p]), $v + ['revision' => '1'])->assertConflict();
        $this->delete(route('social.posts.delete', [$this->account, $p]))->assertRedirect();
        $this->assertSoftDeleted($p);
    }

    public function test_targets_campaigns_tags_audience_and_formula_configuration_are_validated(): void
    {
        $action = app(ManageSocialCatalog::class);
        $campaign = $action->save($this->actor, $this->account, 'campaign', ['name' => 'Initiative', 'kind' => 'initiative', 'active' => 1]);
        $tag = $action->save($this->actor, $this->account, 'tag', ['name' => 'Safety', 'active' => 1]);
        $target = $action->save($this->actor, $this->account, 'target', ['year' => 2026, 'month' => 9, 'metric' => 'impressions', 'value' => 1000, 'active' => 1]);
        $action->save($this->actor, $this->account, 'target', ['year' => 2026, 'month' => 9, 'metric' => 'impressions', 'value' => 2000, 'active' => 0], $target->id);
        $this->assertFalse($target->fresh()->active);
        $audience = $action->save($this->actor, $this->account, 'audience', ['metric_date' => '2026-09-01', 'dimension' => 'location', 'value' => 'Oman', 'percentage' => 80]);
        $this->assertSame('Oman', $audience->value);
        $settings = collect(config('social'))->only(['engagement_components', 'score_weights', 'score_thresholds', 'attention_ratio'])->all();
        $settings['engagement_components'] = ['reactions', 'comments'];
        $action->settings($this->actor, $this->account, $settings);
        $this->assertSame(['reactions', 'comments'], $this->account->fresh()->settings['engagement_components']);
        $this->post(route('social.settings.save', $this->account), array_merge($settings, ['score_weights' => array_fill_keys(array_keys($settings['score_weights']), 0)]))->assertSessionHasErrors('score_weights');
        $action->delete($this->actor, $this->account, 'campaign', $campaign->id);
        $this->assertSoftDeleted($campaign);
        $action->delete($this->actor, $this->account, 'tag', $tag->id);
        $this->assertSoftDeleted($tag);
    }

    public function test_xlsx_import(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'social-xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(ImportSocialMetrics::HEADER));
        $writer->addRow(Row::fromValues(['account', null, '2026-09-01', '2026-09-01', 100, 1, 2, 3, 4, 500, 5]));
        $writer->close();
        try {
            $file = new UploadedFile($path, 'metrics.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $import = app(ImportSocialMetrics::class)->preview($this->actor, $this->account, $file);
            $this->assertSame([], $import->errors);
            app(ImportSocialMetrics::class)->confirm($this->actor, $this->account, $import);
            $this->assertDatabaseHas('social_account_metrics', ['impressions' => 100]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_import_permission_does_not_grant_manual_entry(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo('linkedin.import');
        $file = UploadedFile::fake()->createWithContent('metrics.csv', implode(',', ImportSocialMetrics::HEADER)."\naccount,,2026-09-01,2026-09-01,100,1,1,1,1,1000,10\n");
        $action = app(ImportSocialMetrics::class);
        $preview = $action->preview($actor, $this->account, $file);
        $action->confirm($actor, $this->account, $preview);
        $this->assertDatabaseHas('social_account_metrics', ['source' => 'import', 'impressions' => 100]);
        $this->actingAs($actor)->post(route('social.metrics.save', $this->account), ['subject' => 'account', 'starts_on' => '2026-09-02', 'ends_on' => '2026-09-02', 'impressions' => 100, 'source' => 'import'])->assertForbidden();
    }

    public function test_xlsx_formulas_are_rejected_before_preview(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'social-formula');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(ImportSocialMetrics::HEADER));
        $writer->addRow(Row::fromValues(['account', null, '2026-09-01', '2026-09-01', '=1+1', 1, 2, 3, 4, 500, 5]));
        $writer->close();
        try {
            $this->expectException(ValidationException::class);
            app(ImportSocialMetrics::class)->preview($this->actor, $this->account, new UploadedFile($path, 'metrics.xlsx', null, null, true));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_other_accounts_cannot_be_used_in_post_metric_and_catalog_mutations(): void
    {
        $other = app(ManageSocialCatalog::class)->account($this->actor, ['name' => 'Other', 'identifier' => 'other', 'timezone' => 'UTC', 'active' => 1]);
        $p = SocialPost::create(['social_account_id' => $other->id, 'platform_post_id' => 'p', 'title' => 'Other', 'published_at' => now(), 'content_type_id' => SocialContentType::first()->id, 'language' => 'en']);
        $this->post(route('social.metrics.save', $this->account), ['subject' => 'post', 'post_id' => $p->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-01', 'impressions' => 1])->assertNotFound();
        $this->get(route('social.posts.show', [$this->account, $p]))->assertNotFound();
    }

    public function test_saved_report_summary_is_encrypted_and_revoked_after_source_visibility_changes(): void
    {
        $this->post(route('social.reports.save', $this->account), ['name' => 'September', 'summary' => 'Private management note'])->assertRedirect();
        $report = SocialReport::firstOrFail();
        $this->assertSame('Private management note', $report->summary);
        $this->assertStringNotContainsString('Private management note', $report->getRawOriginal('summary'));
        $this->account->increment('revision');
        $this->get(route('social.export', [$this->account, 'xlsx', 'report_id' => $report->id]))->assertForbidden();
    }

    public function test_annual_distribution_preserves_total_and_refuses_overwriting_months(): void
    {
        $target = app(ManageSocialCatalog::class)->save($this->actor, $this->account, 'target', ['year' => 2026, 'month' => 0, 'metric' => 'posts', 'value' => 125, 'active' => 1]);
        $this->post(route('social.targets.distribute', [$this->account, $target]))->assertRedirect();
        $this->assertEquals(125, SocialTarget::where('social_account_id', $this->account->id)->where('month', '>', 0)->sum('value'));
        $this->assertSame(12, SocialTarget::where('social_account_id', $this->account->id)->where('month', '>', 0)->count());
        $this->post(route('social.targets.distribute', [$this->account, $target]))->assertSessionHasErrors('metric');
    }
}
