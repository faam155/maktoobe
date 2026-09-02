<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Queries\Dashboard\AdminDashboardQuery;
use App\Queries\Events\PortalEventIndexQuery;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\EventCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, EventCategorySeeder::class]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    private function payload(User $organizer, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Annual Forum', 'description' => 'A focused annual gathering.',
            'starts_at' => '2026-11-10T09:00', 'ends_at' => '2026-11-10T12:00', 'timezone' => 'Asia/Muscat',
            'location' => 'Muscat', 'organizer_id' => $organizer->id, 'status' => 'planned', 'visibility' => 'all_users',
        ], $overrides);
    }

    public function test_event_manager_can_create_update_and_assign_selected_users(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $member = $this->user(Access::STANDARD_USER);
        $response = $this->signIn($manager)->post('/admin/events', $this->payload($manager, ['visibility' => 'selected_users', 'user_ids' => [$member->id]]));
        $event = Event::firstOrFail();
        $response->assertRedirect(route('admin.events.show', $event));
        $this->assertSame('Asia/Muscat', $event->timezone);
        $this->assertSame('2026-11-10 05:00:00', $event->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($event->allowedUsers->contains($member));
        $this->assertDatabaseHas('event_activities', ['event_id' => $event->id, 'action' => 'event.created']);

        $this->put('/admin/events/'.$event->slug, $this->payload($manager, ['title' => 'Updated Forum', 'visibility' => 'private']))->assertRedirect();
        $this->assertSame('Updated Forum', $event->fresh()->title);
        $this->assertCount(0, $event->fresh()->allowedUsers);
    }

    public function test_visibility_scope_handles_all_private_selected_user_selected_role_and_draft(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $member = $this->user(Access::STANDARD_USER);
        $other = $this->user(Access::STANDARD_USER);
        $standardRole = Role::findByName(Access::STANDARD_USER, 'web');
        $all = Event::factory()->for($manager, 'organizer')->create(['visibility' => 'all_users']);
        $private = Event::factory()->for($manager, 'organizer')->create(['visibility' => 'private']);
        $selectedUser = Event::factory()->for($manager, 'organizer')->create(['visibility' => 'selected_users']);
        $selectedUser->allowedUsers()->attach($member, ['granted_by' => $manager->id]);
        $selectedRole = Event::factory()->for($manager, 'organizer')->create(['visibility' => 'selected_roles']);
        $selectedRole->allowedRoles()->attach($standardRole, ['granted_by' => $manager->id]);
        $draft = Event::factory()->for($member, 'organizer')->create(['status' => 'draft', 'visibility' => 'all_users']);

        $memberPage = $this->signIn($member)->get('/app/events?period=all')->assertOk();
        $memberPage->assertSee($all->title)->assertDontSee($private->title)->assertSee($selectedUser->title)->assertSee($selectedRole->title)->assertSee($draft->title);
        $otherPage = $this->signIn($other)->get('/app/events?period=all')->assertOk();
        $otherPage->assertSee($all->title)->assertDontSee($private->title)->assertDontSee($selectedUser->title)->assertSee($selectedRole->title)->assertDontSee($draft->title);
        $this->get('/app/events/'.$private->slug)->assertForbidden();
        $this->signIn($manager)->get('/app/events/'.$private->slug)->assertOk();
    }

    public function test_role_assignment_is_required_and_controls_access(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $this->signIn($manager)->post('/admin/events', $this->payload($manager, ['visibility' => 'selected_roles', 'role_ids' => []]))->assertSessionHasErrors('role_ids');
        $role = Role::findByName(Access::STANDARD_USER, 'web');
        $this->post('/admin/events', $this->payload($manager, ['visibility' => 'selected_roles', 'role_ids' => [$role->id]]))->assertRedirect();
        $this->assertDatabaseHas('event_role_access', ['event_id' => Event::first()->id, 'role_id' => $role->id]);
    }

    public function test_status_transitions_cancel_and_terminal_protection_are_enforced(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $event = Event::factory()->for($manager, 'organizer')->create(['status' => EventStatus::Planned]);
        $this->signIn($manager)->patch('/admin/events/'.$event->slug.'/status', ['status' => 'confirmed'])->assertRedirect();
        $this->patch('/admin/events/'.$event->slug.'/status', ['status' => 'in_progress'])->assertRedirect();
        $this->patch('/admin/events/'.$event->slug.'/status', ['status' => 'completed'])->assertRedirect();
        $this->patch('/admin/events/'.$event->slug.'/status', ['status' => 'planned'])->assertSessionHasErrors('status');
        $this->assertSame(EventStatus::Completed, $event->fresh()->status);

        $cancelled = Event::factory()->for($manager, 'organizer')->create(['status' => 'confirmed']);
        $this->patch('/admin/events/'.$cancelled->slug.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->assertDatabaseHas('event_activities', ['event_id' => $cancelled->id, 'action' => 'event.cancelled']);
    }

    public function test_duplicate_is_a_private_draft_without_inherited_audience(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $member = $this->user(Access::STANDARD_USER);
        $event = Event::factory()->for($manager, 'organizer')->create(['visibility' => 'selected_users']);
        $event->allowedUsers()->attach($member, ['granted_by' => $manager->id]);
        $this->signIn($manager)->post('/admin/events/'.$event->slug.'/duplicate')->assertRedirect();
        $copy = Event::whereKeyNot($event->id)->firstOrFail();
        $this->assertSame('draft', $copy->status->value);
        $this->assertSame('private', $copy->visibility->value);
        $this->assertCount(0, $copy->allowedUsers);
    }

    public function test_management_routes_and_mutations_require_manage_events(): void
    {
        $standard = $this->user(Access::STANDARD_USER);
        $event = Event::factory()->for($standard, 'organizer')->create(['visibility' => 'private']);
        $this->signIn($standard)->get('/admin/events')->assertForbidden();
        $this->get('/admin/events/'.$event->slug)->assertForbidden();
        $this->post('/admin/events', $this->payload($standard))->assertForbidden();
        $this->put('/admin/events/'.$event->slug, $this->payload($standard))->assertForbidden();
        $this->patch('/admin/events/'.$event->slug.'/status', ['status' => 'cancelled'])->assertForbidden();
        $this->post('/admin/events/'.$event->slug.'/duplicate')->assertForbidden();
        $this->delete('/admin/events/'.$event->slug)->assertForbidden();
    }

    public function test_delete_is_soft_and_event_manager_has_only_scoped_admin_access(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $event = Event::factory()->for($manager, 'organizer')->create();
        $this->signIn($manager)->get('/admin/events')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->delete('/admin/events/'.$event->slug)->assertRedirect('/admin/events');
        $this->assertSoftDeleted('events', ['id' => $event->id]);
        $this->assertDatabaseHas('event_activities', ['event_id' => $event->id, 'action' => 'event.deleted']);
    }

    public function test_validation_rejects_invalid_dates_organizer_and_audience(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $disabled = User::factory()->create(['status' => 'disabled']);
        $this->signIn($manager)->post('/admin/events', $this->payload($disabled, ['ends_at' => '2026-11-10T08:00', 'visibility' => 'selected_users']))
            ->assertSessionHasErrors(['ends_at', 'organizer_id', 'user_ids']);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_search_and_pagination_apply_visibility_before_counts(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $member = $this->user(Access::STANDARD_USER);
        Event::factory()->count(13)->for($manager, 'organizer')->create(['title' => 'Visible conference']);
        $hidden = Event::factory()->for($manager, 'organizer')->create(['title' => 'Secret conference', 'visibility' => 'private']);
        $page = app(PortalEventIndexQuery::class)->handle($member, ['search' => 'conference', 'period' => 'all']);
        $this->assertSame(13, $page->total());
        $this->assertCount(12, $page->items());
        $this->signIn($member)->get('/app/events?period=all&search=Secret')->assertOk()->assertDontSee($hidden->title);
    }

    public function test_creator_is_server_owned_and_status_cannot_bypass_transition_action(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $other = $this->user(Access::STANDARD_USER);
        $this->signIn($manager)->post('/admin/events', $this->payload($manager, ['created_by' => $other->id]))->assertRedirect();
        $event = Event::firstOrFail();
        $this->assertSame($manager->id, $event->created_by);
        $this->put('/admin/events/'.$event->slug, $this->payload($manager, ['status' => 'completed']))->assertRedirect();
        $this->assertSame(EventStatus::Planned, $event->fresh()->status);
        $this->post('/admin/events', $this->payload($manager, ['status' => 'completed']))->assertSessionHasErrors('status');
    }

    public function test_revoked_audience_and_disabled_users_lose_access(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $member = $this->user(Access::STANDARD_USER);
        $event = Event::factory()->for($manager, 'organizer')->create(['visibility' => 'selected_users']);
        $event->allowedUsers()->attach($member);
        $this->signIn($member)->get('/app/events/'.$event->slug)->assertOk();
        $event->allowedUsers()->detach($member);
        $this->get('/app/events/'.$event->slug)->assertForbidden();
        $member->forceFill(['status' => 'disabled'])->save();
        $this->get('/app/events')->assertRedirect();
    }

    public function test_analytics_capability_does_not_leak_private_event_counts(): void
    {
        $manager = $this->user(Access::EVENT_MANAGER);
        $reader = $this->user(Access::STANDARD_USER);
        $reader->givePermissionTo(['access-admin', 'view-analytics']);
        Event::factory()->for($manager, 'organizer')->create(['visibility' => 'private']);
        Event::factory()->for($manager, 'organizer')->create(['visibility' => 'all_users']);
        $data = app(AdminDashboardQuery::class)->get($reader);
        $this->assertSame(1, $data['eventMetrics']->firstWhere('key', 'upcoming_events')['value']);
    }
}
