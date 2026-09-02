<?php

namespace Tests\Feature;

use App\Actions\Events\ChangeEventStatus;
use App\Actions\Events\UploadEventReport;
use App\Actions\Notifications\RecordWorkspaceNotice;
use App\Actions\Notifications\SendSystemNotice;
use App\Actions\Prompts\ChangePromptStatus;
use App\Enums\EventStatus;
use App\Enums\PromptStatus;
use App\Jobs\DeliverWorkspaceNotice;
use App\Models\Event;
use App\Models\Prompt;
use App\Models\User;
use App\Models\WorkspaceDatabaseNotification;
use App\Models\WorkspaceNotice;
use App\Queries\Notifications\NotificationInbox;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Queue::fake();
    }

    private function user(string $role = Access::STANDARD_USER): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    private function deliver(string $kind, array $refs = [], ?string $key = null): WorkspaceNotice
    {
        $notice = app(RecordWorkspaceNotice::class)->handle($kind, $key ?? (string) Str::uuid(), $refs);
        (new DeliverWorkspaceNotice($notice->id))->handle();

        return $notice;
    }

    public function test_delivery_uses_current_audience_and_never_copies_resource_content(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $stranger = $this->user();
        $disabled = User::factory()->create(['status' => 'disabled']);
        $unverified = User::factory()->unverified()->create();
        $event = Event::factory()->create(['organizer_id' => $owner->id, 'visibility' => 'selected_users']);
        $event->allowedUsers()->sync([$member->id, $disabled->id, $unverified->id]);
        $notice = $this->deliver('event_published', ['event_id' => $event->id]);
        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(['notice_id' => $notice->id], $member->notifications()->first()->data);
        foreach ([$stranger, $disabled, $unverified] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $id = $member->notifications()->first()->id;
        $event->allowedUsers()->detach($member);
        $this->assertSame(0, app(NotificationInbox::class)->unread($member));
        $this->signIn($member)->get('/app/notifications')->assertOk()->assertDontSee($event->title);
        $this->post("/app/notifications/$id/open")->assertNotFound();
        $this->post("/app/notifications/$id/read")->assertNotFound();
    }

    public function test_read_dismiss_and_replay_are_owner_scoped_and_duplicate_safe(): void
    {
        $member = $this->user();
        $other = $this->user();
        $notice = $this->deliver('system');
        $id = $member->notifications()->first()->id;
        $this->signIn($other)->post("/app/notifications/$id/read")->assertNotFound();
        $this->delete("/app/notifications/$id")->assertNotFound();
        $this->signIn($member)->post("/app/notifications/$id/read")->assertRedirect();
        $this->assertSame(0, app(NotificationInbox::class)->unread($member));
        $this->assertSame(1, app(NotificationInbox::class)->unread($other));
        $this->delete("/app/notifications/$id")->assertRedirect();
        $notice->update(['completed_at' => null, 'last_user_id' => 0]);
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(0, app(NotificationInbox::class)->query($member)->count());
        $this->post('/app/notifications/read-all')->assertRedirect();
        $this->assertSame(1, app(NotificationInbox::class)->unread($other));
    }

    public function test_prompt_withdrawal_removes_notification_access_and_new_users_are_not_backfilled(): void
    {
        $member = $this->user();
        $prompt = Prompt::factory()->published()->create();
        $notice = app(RecordWorkspaceNotice::class)->handle('prompt_published', 'prompt-test', ['prompt_id' => $prompt->id]);
        $late = $this->user();
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(1, app(NotificationInbox::class)->unread($member));
        $this->assertSame(0, $late->notifications()->count());
        $prompt->update(['status' => 'draft']);
        $this->assertSame(0, app(NotificationInbox::class)->unread($member));
    }

    public function test_reminders_are_range_limited_reschedule_aware_and_idempotent(): void
    {
        $member = $this->user();
        $event = Event::factory()->create(['starts_at' => now()->addHours(4), 'ends_at' => now()->addHours(5)]);
        Event::factory()->create(['starts_at' => now()->addDays(3)]);
        Event::factory()->create(['status' => 'cancelled', 'starts_at' => now()->addHours(2)]);
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertSame(1, WorkspaceNotice::where('kind', 'event_reminder')->count());
        $notice = WorkspaceNotice::firstOrFail();
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(1, app(NotificationInbox::class)->unread($member));
        $event->update(['starts_at' => now()->addHours(6), 'ends_at' => now()->addHours(7)]);
        $this->assertSame(0, app(NotificationInbox::class)->unread($member));
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertSame(2, WorkspaceNotice::count());
    }

    public function test_event_publish_assignment_and_cancel_record_domain_notices(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $member = $this->user();
        $event = Event::factory()->create(['organizer_id' => $manager->id, 'status' => 'draft', 'visibility' => 'selected_users']);
        $event->allowedUsers()->sync([$member->id]);
        app(ChangeEventStatus::class)->handle($manager, $event, EventStatus::Planned);
        $this->assertDatabaseHas('workspace_notices', ['kind' => 'event_published', 'event_id' => $event->id]);
        $this->assertDatabaseHas('workspace_notices', ['kind' => 'event_assigned', 'target_user_id' => $member->id, 'broadcast' => false]);
        app(ChangeEventStatus::class)->handle($manager, $event->fresh(), EventStatus::Cancelled);
        $this->assertDatabaseHas('workspace_notices', ['kind' => 'event_cancelled', 'event_id' => $event->id]);
        Queue::assertPushed(DeliverWorkspaceNotice::class);
    }

    public function test_system_notice_requires_permission_confirmation_and_valid_bilingual_content(): void
    {
        $member = $this->user();
        $admin = $this->user(Access::SUPER_ADMINISTRATOR);
        $data = ['title_en' => 'Maintenance', 'title_ar' => 'صيانة', 'body_en' => 'Scheduled maintenance.', 'body_ar' => 'صيانة مجدولة.', 'operation_id' => (string) Str::uuid(), 'confirm' => '1', 'target_user_id' => $member->id];
        $this->signIn($member)->post('/app/notifications/system', $data)->assertForbidden();
        $this->signIn($admin)->post('/app/notifications/system', [])->assertSessionHasErrors(['title_en', 'title_ar', 'confirm']);
        app(SendSystemNotice::class)->handle($admin, $data);
        app(SendSystemNotice::class)->handle($admin, $data);
        $this->assertSame(1, WorkspaceNotice::count());
        $notice = WorkspaceNotice::firstOrFail();
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertDatabaseHas('account_audits', ['action' => 'notification.system_queued']);
    }

    public function test_report_notifications_respect_version_lifecycle_and_open_the_reports_tab(): void
    {
        Storage::fake('local');
        $manager = $this->user(Access::EVENT_MANAGER);
        $viewer = $this->user();
        $event = Event::factory()->create(['organizer_id' => $manager->id]);
        app(UploadEventReport::class)->handle($manager, $event, [
            'type' => 'PRE_EVENT', 'title' => 'Preparation',
            'file' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
        ]);
        $notice = WorkspaceNotice::where('kind', 'report_uploaded')->firstOrFail();
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(1, app(NotificationInbox::class)->unread($viewer));
        $id = $viewer->notifications()->firstOrFail()->id;
        $this->signIn($viewer)->post("/app/notifications/$id/open")->assertRedirect(route('events.reports.index', $event).'#PRE_EVENT');
        $notice->reportVersion->delete();
        $this->assertSame(0, app(NotificationInbox::class)->query($viewer)->count());
        $this->post("/app/notifications/$id/open")->assertNotFound();
    }

    public function test_prompt_publish_hook_and_selected_role_delivery_remain_authorized(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $manager = $this->user(Access::EVENT_MANAGER);
        $stranger = $this->user();
        $prompt = Prompt::factory()->create(['owner_id' => $admin->id, 'visibility' => 'selected_roles']);
        $prompt->allowedRoles()->attach(Role::findByName(Access::EVENT_MANAGER), ['granted_by' => $admin->id, 'created_at' => now()]);
        app(ChangePromptStatus::class)->handle($admin, $prompt, PromptStatus::Published);
        app(ChangePromptStatus::class)->handle($admin, $prompt, PromptStatus::Published);
        $this->assertSame(1, WorkspaceNotice::where('kind', 'prompt_published')->count());
        $notice = WorkspaceNotice::firstOrFail();
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(1, app(NotificationInbox::class)->unread($manager));
        $this->assertSame(0, $stranger->notifications()->count());
        $manager->removeRole(Access::EVENT_MANAGER);
        $this->assertSame(0, app(NotificationInbox::class)->unread($manager));
    }

    public function test_delivery_rechecks_revoked_access_and_resumes_bounded_batches(): void
    {
        $member = $this->user();
        $event = Event::factory()->create(['visibility' => 'selected_users']);
        $event->allowedUsers()->attach($member);
        $notice = app(RecordWorkspaceNotice::class)->handle('event_published', 'revoke-before-worker', ['event_id' => $event->id]);
        $event->allowedUsers()->detach($member);
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertSame(0, $member->notifications()->count());

        User::factory()->count(51)->create();
        $notice = app(RecordWorkspaceNotice::class)->handle('system', 'batch-test');
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertNull($notice->fresh()->completed_at);
        $this->assertSame(50, WorkspaceDatabaseNotification::where('notice_id', $notice->id)->count());
        (new DeliverWorkspaceNotice($notice->id))->handle();
        (new DeliverWorkspaceNotice($notice->id))->handle();
        $this->assertNotNull($notice->fresh()->completed_at);
        $this->assertSame(User::count(), WorkspaceDatabaseNotification::where('notice_id', $notice->id)->count());
    }

    public function test_inbox_paginates_and_read_all_only_changes_current_user(): void
    {
        $member = $this->user();
        $other = $this->user();
        for ($i = 0; $i < 21; $i++) {
            $this->deliver('system');
        }
        $this->signIn($member)->get('/app/notifications')->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->count() === 20 && $rows->total() === 21);
        $this->post('/app/notifications/read-all')->assertRedirect();
        $this->assertSame(0, app(NotificationInbox::class)->unread($member));
        $this->assertSame(21, app(NotificationInbox::class)->unread($other));
        $this->get('/app/notifications?filter=unread')->assertViewHas('notifications', fn ($rows) => $rows->total() === 0);
    }
}
