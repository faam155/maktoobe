<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use App\Queries\Events\EventCalendarQuery;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\EventCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, EventCategorySeeder::class]);
    }

    private function member(): User
    {
        $user = User::factory()->create(['timezone' => 'Asia/Muscat']);
        $user->assignRole(Access::STANDARD_USER);
        $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version]);

        return $user;
    }

    private function event(array $attributes = []): Event
    {
        return Event::factory()->create(array_merge(['starts_at' => '2027-01-15 08:00:00', 'ends_at' => '2027-01-15 10:00:00'], $attributes));
    }

    public function test_month_week_and_agenda_have_bounded_local_ranges(): void
    {
        $actor = $this->member();
        foreach (['month' => 35, 'week' => 7, 'list' => 31] as $view => $count) {
            $data = app(EventCalendarQuery::class)->handle($actor, ['view' => $view, 'date' => '2027-01-15']);
            $this->assertCount($count, $data['days']);
            $this->get('/app/calendar?view='.$view.'&date=2027-01-15')->assertOk();
        }
    }

    public function test_half_open_overlap_and_local_midnight_are_respected(): void
    {
        $actor = $this->member();
        $before = $this->event(['starts_at' => '2027-01-14 18:00:00', 'ends_at' => '2027-01-14 20:00:00']);
        $overlap = $this->event(['starts_at' => '2027-01-14 19:00:00', 'ends_at' => '2027-01-15 21:00:00']);
        $after = $this->event(['starts_at' => '2027-01-15 20:00:00', 'ends_at' => '2027-01-15 22:00:00']);
        $data = app(EventCalendarQuery::class)->handle($actor, ['from' => '2027-01-15', 'to' => '2027-01-15']);
        $this->assertSame([$overlap->id], $data['events']->pluck('id')->all());
        $this->assertCount(1, $data['days']);
    }

    public function test_audience_filtering_precedes_results_and_filter_options(): void
    {
        $actor = $this->member();
        $private = $this->event(['title' => 'Hidden private calendar title', 'visibility' => 'private']);
        $draft = $this->event(['title' => 'Hidden draft calendar title', 'status' => 'draft']);
        $selected = $this->event(['visibility' => 'selected_users']);
        $selected->allowedUsers()->attach($actor);
        $roleEvent = $this->event(['visibility' => 'selected_roles']);
        $roleEvent->allowedRoles()->attach($actor->roles->first());
        $data = app(EventCalendarQuery::class)->handle($actor, ['date' => '2027-01-15']);
        $this->assertEqualsCanonicalizing([$selected->id, $roleEvent->id], $data['events']->pluck('id')->all());
        $this->assertFalse($data['organizers']->contains('id', $private->organizer_id));
        $this->get('/app/calendar?date=2027-01-15')->assertOk()->assertDontSee($private->title)->assertDontSee($draft->title);
        $this->get('/admin/calendar')->assertForbidden();
        $this->get('/app/calendar?visibility=private')->assertSessionHasErrors('visibility');
    }

    public function test_manager_filters_status_category_organizer_and_visibility(): void
    {
        $actor = $this->member();
        $actor->assignRole(Access::EVENT_MANAGER);
        $category = EventCategory::firstOrFail();
        $match = $this->event(['category_id' => $category->id, 'organizer_id' => $actor->id, 'visibility' => 'private']);
        $this->event();
        $filters = ['date' => '2027-01-15', 'status' => 'confirmed', 'visibility' => 'private', 'category' => $category->id, 'organizer' => $actor->id];
        $data = app(EventCalendarQuery::class)->handle($actor, $filters, true);
        $this->assertSame([$match->id], $data['events']->pluck('id')->all());
        $this->get('/admin/calendar?'.http_build_query($filters))->assertOk()->assertSee($match->title);
    }

    public function test_invalid_and_unbounded_ranges_are_rejected(): void
    {
        $this->member();
        foreach (['from=2027-01-01&to=2027-12-31', 'from=2027-01-15&to=2027-01-14', 'from=2027-01-15', 'date=not-a-date', 'view=day'] as $query) {
            $this->get('/app/calendar?'.$query)->assertRedirect()->assertSessionHasErrors();
        }
    }

    public function test_dense_ranges_paginate_and_exclude_distant_events(): void
    {
        $actor = $this->member();
        Event::factory()->count(101)->create(['organizer_id' => $actor->id, 'starts_at' => '2027-01-15 08:00:00', 'ends_at' => '2027-01-15 09:00:00']);
        $this->event(['starts_at' => '2029-01-15 08:00:00', 'ends_at' => '2029-01-15 09:00:00']);
        $this->get('/app/calendar?date=2027-01-15')->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 101 && $events->count() === 100);
        $this->get('/app/calendar?date=2027-01-15&page=2')->assertOk()->assertViewHas('events', fn ($events) => $events->count() === 1);
    }
}
