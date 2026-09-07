<?php

namespace Tests\Feature;

use App\Actions\Audit\RecordAudit;
use App\Actions\Events\UploadEventReport;
use App\Actions\Identity\RecordAccountAudit;
use App\Enums\PromptVisibility;
use App\Models\AiConversation;
use App\Models\AiRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventFile;
use App\Models\Prompt;
use App\Models\PromptFavorite;
use App\Models\PromptUse;
use App\Models\User;
use App\Queries\Analytics\AnalyticsQuery;
use App\Support\Authorization\Access;
use App\Support\Reporting\ReportPeriod;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Access::SUPER_ADMINISTRATOR);

        return $u;
    }

    private function signIn(User $u): static
    {
        return $this->actingAs($u)->withSession(['auth.security_version' => $u->security_version, 'auth.confirmed_at' => time()]);
    }

    public function test_dates_use_local_half_open_boundaries_and_reject_excessive_ranges(): void
    {
        $u = $this->admin();
        $u->forceFill(['timezone' => 'Asia/Muscat'])->save();
        $p = ReportPeriod::from($u, ['period' => 'custom', 'from' => '2026-09-03', 'to' => '2026-09-03']);
        $this->assertSame('2026-09-02 20:00:00', $p->start->toDateTimeString());
        $this->assertSame('2026-09-03 20:00:00', $p->end->toDateTimeString());
        $this->signIn($u)->get('/admin/analytics?period=custom&from=2020-01-01&to=2026-09-03')->assertSessionHasErrors('to');
        $this->get('/admin/audit-logs?period=custom&from=2026-09-04&to=2026-09-03')->assertSessionHasErrors('to');
    }

    public function test_metrics_and_rankings_exclude_other_private_workspaces_and_deleted_records(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 3)->startOfDay()->addHours(12));
        $u = $this->admin();
        $other = User::factory()->create();
        $visible = Prompt::factory()->published()->create(['created_at' => now(), 'title' => 'Visible Library']);
        $secret = Prompt::factory()->published(PromptVisibility::Private)->create(['owner_id' => $other->id, 'title' => 'Secret Library']);
        Prompt::factory()->create(['owner_id' => $u->id, 'source' => 'personal']);
        Prompt::factory()->create(['owner_id' => $other->id, 'source' => 'personal', 'title' => 'Private workspace']);
        foreach ([$visible, $visible, $secret] as $prompt) {
            PromptUse::create(['prompt_id' => $prompt->id, 'user_id' => $u->id, 'kind' => 'copy', 'client_operation_id' => Str::uuid()]);
        }
        PromptFavorite::create(['prompt_id' => $visible->id, 'user_id' => $u->id]);
        PromptFavorite::create(['prompt_id' => $visible->id, 'user_id' => $other->id]);
        Event::factory()->create(['starts_at' => now()->addHours(1), 'ends_at' => now()->addHours(2), 'status' => 'confirmed']);
        $deleted = Event::factory()->create(['starts_at' => now(), 'ends_at' => now()->addHour()]);
        $deleted->delete();
        $data = app(AnalyticsQuery::class)->get($u, ReportPeriod::from($u, ['period' => 'today']));
        $this->assertSame(1, $data['metrics']['administrator_prompts']);
        $this->assertSame(1, $data['metrics']['personal_prompts']);
        $this->assertSame(1, $data['metrics']['favorite_prompts']);
        $this->assertSame(1, $data['metrics']['total_events']);
        $this->assertSame(1, $data['metrics']['upcoming_events']);
        $this->assertSame([['name' => 'Visible Library', 'count' => 2]], $data['topPrompts']);
        $this->signIn($u)->get('/admin/analytics?period=today')->assertOk()->assertDontSee('Secret Library')->assertDontSee('Private workspace');
    }

    public function test_token_totals_count_successes_once_and_report_unknown_usage(): void
    {
        $u = $this->admin();
        $other = User::factory()->create();
        foreach ([[$u, 'completed', 30], [$u, 'completed', null], [$u, 'failed', 99], [$other, 'completed', 500]] as [$owner,$status,$tokens]) {
            $c = AiConversation::create(['user_id' => $owner->id, 'title' => 'private conversation', 'model' => 'test']);
            $m = $c->messages()->create(['role' => 'user', 'content' => 'SECRET CONTENT', 'model' => 'test', 'created_at' => now()]);
            AiRequest::create(['user_id' => $owner->id, 'conversation_id' => $c->id, 'user_message_id' => $m->id, 'model' => 'test', 'status' => $status, 'settings_snapshot' => [], 'client_operation_id' => Str::uuid(), 'total_tokens' => $tokens, 'requested_at' => now()]);
        }
        $data = app(AnalyticsQuery::class)->get($u, ReportPeriod::from($u, []));
        $this->assertSame(3, $data['metrics']['ai_requests']);
        $this->assertSame(3, $data['metrics']['ai_conversations']);
        $this->assertSame(30, $data['metrics']['ai_tokens']);
        $this->assertSame(1, $data['metrics']['unknown_tokens']);
        $this->signIn($u)->get('/admin/analytics')->assertDontSee('SECRET CONTENT')->assertDontSee('private conversation');
    }

    public function test_audit_writes_exclude_secrets_and_rollback_with_mutations(): void
    {
        $u = $this->admin();
        app(RecordAccountAudit::class)->handle($u, 'account.updated_by_admin', ['email_changed' => true, 'password' => 'secret-pass', 'api_key' => 'secret-key', 'reason' => 'private notes', 'before' => ['content' => 'secret-body', 'permissions' => ['manage-users', ['token' => 'leak']]]], $u);
        $log = AuditLog::firstOrFail();
        $this->assertEquals(['email_changed' => true, 'before' => ['permissions' => ['manage-users']]], $log->metadata);
        $this->assertStringNotContainsString('secret', json_encode(DB::table('account_audits')->first()));
        DB::beginTransaction();
        app(RecordAudit::class)->handle($u->id, 'event.created', 'event', 42);
        DB::rollBack();
        $this->assertSame(1, AuditLog::count());
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'forged']);
    }

    public function test_legacy_import_is_sanitized_idempotent_and_event_hooks_capture_resources(): void
    {
        $u = $this->admin();
        $event = Event::factory()->create();
        EventActivity::create(['event_id' => $event->id, 'actor_id' => $u->id, 'action' => 'event.visibility_changed', 'metadata' => ['from' => 'private', 'to' => 'all_users', 'content' => 'secret'], 'created_at' => now()]);
        DB::table('account_audits')->insert(['user_id' => $u->id, 'actor_id' => $u->id, 'action' => 'prompt.deleted', 'metadata' => json_encode(['prompt_id' => 41, 'password' => 'legacy-secret']), 'created_at' => now()]);
        $this->artisan('audit:import-legacy')->assertSuccessful();
        $this->artisan('audit:import-legacy')->assertSuccessful();
        $this->assertSame(2, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', ['resource_type' => 'event', 'resource_id' => $event->id, 'action' => 'event.visibility_changed']);
        $this->assertStringNotContainsString('secret', AuditLog::all()->toJson());
    }

    public function test_configuration_records_only_changed_keys_and_never_credentials(): void
    {
        config(['ai.openai.key' => 'secret-key']);
        $this->artisan('audit:configuration')->assertSuccessful();
        $this->assertSame(0, AuditLog::count());
        config(['ai.max_output_tokens' => 875, 'ai.openai.key' => 'rotated-secret']);
        $this->artisan('audit:configuration')->assertSuccessful();
        $this->artisan('audit:configuration')->assertSuccessful();
        $this->assertSame(1, AuditLog::count());
        $this->assertSame(['changed_keys' => ['max_output_tokens']], AuditLog::first()->metadata);
        $this->assertNull(AuditLog::first()->actor_id);
        $this->assertStringNotContainsString('secret', json_encode(DB::table('audited_configurations')->get()));
    }

    public function test_access_requires_separate_permissions_and_read_only_routes(): void
    {
        foreach (['en', 'ar'] as $locale) {
            $this->assertTrue(Lang::hasForLocale('admin.permissions.view-audit-logs', $locale));
        }
        $member = User::factory()->create();
        $member->assignRole(Access::STANDARD_USER);
        $this->signIn($member)->get('/admin/analytics')->assertForbidden();
        $this->get('/admin/audit-logs')->assertForbidden();
        $u = $this->admin();
        $this->signIn($u);
        $this->get('/admin/analytics')->assertOk();
        $this->get('/admin/audit-logs')->assertOk();
        $this->post('/admin/audit-logs')->assertStatus(405);
        $this->delete('/admin/audit-logs/1')->assertStatus(405);
        $u->syncRoles([]);
        $u->givePermissionTo(['access-admin', 'view-analytics']);
        $this->get('/admin/analytics')->assertOk()->assertDontSee('data-metric="total_users"', false);
        $this->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_event_files_reports_and_generation_usage_are_counted_without_version_duplication(): void
    {
        Storage::fake('local');
        Queue::fake();
        $u = $this->admin();
        $event = Event::factory()->create(['organizer_id' => $u->id]);
        EventFile::factory()->create(['event_id' => $event->id, 'category' => 'photos']);
        EventFile::factory()->create(['event_id' => $event->id, 'category' => 'photos', 'scan_status' => 'pending']);
        for ($i = 0; $i < 2; $i++) {
            app(UploadEventReport::class)->handle($u, $event, ['type' => 'PRE_EVENT', 'title' => 'Report', 'file' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")]);
        }
        $communication = $event->communications()->create(['type' => 'general_copy', 'language' => 'en', 'revision_number' => 1, 'content' => 'private content']);
        $communication->generations()->create(['event_id' => $event->id, 'user_id' => $u->id, 'client_operation_id' => Str::uuid(), 'base_revision' => 1, 'operation' => 'generate', 'model' => 'test', 'status' => 'completed', 'input_snapshot' => [], 'settings_snapshot' => [], 'total_tokens' => 25]);
        $data = app(AnalyticsQuery::class)->get($u, ReportPeriod::from($u, []));
        $this->assertSame(1, $data['metrics']['event_photos']);
        $this->assertSame(1, $data['metrics']['event_reports']);
        $this->assertSame(1, $data['metrics']['event_communications']);
        $this->assertSame(1, $data['metrics']['ai_requests']);
        $this->assertSame(25, $data['metrics']['ai_tokens']);
        $this->assertSame(2, AuditLog::where('action', 'event.report_version_uploaded')->count());
    }

    public function test_disabled_accounts_cannot_open_reporting_and_event_audience_is_rechecked(): void
    {
        $u = $this->admin();
        $u->syncRoles([]);
        $u->givePermissionTo(['access-admin', 'view-analytics']);
        $event = Event::factory()->create(['visibility' => 'selected_users', 'starts_at' => now(), 'ends_at' => now()->addHour()]);
        $event->allowedUsers()->attach($u);
        $data = app(AnalyticsQuery::class)->get($u, ReportPeriod::from($u, []));
        $this->assertSame(1, $data['metrics']['total_events']);
        $event->allowedUsers()->detach($u);
        $data = app(AnalyticsQuery::class)->get($u, ReportPeriod::from($u, []));
        $this->assertSame(0, $data['metrics']['total_events']);
        $u->forceFill(['status' => 'disabled'])->save();
        $this->signIn($u)->get('/admin/analytics')->assertRedirect(route('login'));
    }

    public function test_audit_filters_and_pagination_hide_unauthorized_resource_types(): void
    {
        $u = $this->admin();
        for ($i = 1; $i <= 32; $i++) {
            app(RecordAudit::class)->handle($u->id, 'event.updated', 'event', $i, ['event_id' => $i]);
        }
        app(RecordAudit::class)->handle($u->id, 'system.configuration_changed', 'system', null);
        $u->syncRoles([]);
        $u->givePermissionTo(['access-admin', 'view-audit-logs', 'manage-events']);
        $this->signIn($u)->get('/admin/audit-logs')->assertOk()->assertViewHas('logs', fn ($rows) => $rows->total() === 32 && $rows->count() === 30)->assertDontSee('system.configuration_changed');
        $this->get('/admin/audit-logs?resource_type=event&resource_id=12&action=event.updated&actor_id='.$u->id)->assertViewHas('logs', fn ($rows) => $rows->total() === 1);
        $this->get('/admin/audit-logs?search=no-match')->assertViewHas('logs', fn ($rows) => $rows->total() === 0);
        $this->get('/admin/audit-logs?resource_type=system')->assertSessionHasErrors('resource_type');
    }
}
