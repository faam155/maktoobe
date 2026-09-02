<?php

namespace Tests\Feature;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\User;
use App\Queries\Dashboard\AdminDashboardQuery;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function userWithRole(?string $role = Access::STANDARD_USER, array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function signedIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version]);
    }

    public function test_portal_dashboard_requires_an_active_verified_account(): void
    {
        $this->get('/app')->assertRedirect(route('login'));
        $this->signedIn($this->userWithRole(overrides: ['email_verified_at' => null]))->get('/app')->assertRedirect(route('verification.notice'));
        $disabled = $this->userWithRole(overrides: ['status' => 'disabled']);
        $this->signedIn($disabled)->get('/app')->assertRedirect(route('login'));
    }

    public function test_standard_user_sees_a_personalized_dashboard_without_fake_data_or_admin_navigation(): void
    {
        $user = $this->userWithRole(overrides: ['name' => 'Portal Member']);

        $this->signedIn($user)->get('/app')->assertOk()
            ->assertSee('Welcome back, Portal Member')
            ->assertSee('Recent AI conversations')
            ->assertSee('Favorite prompts')
            ->assertSee('Upcoming events')
            ->assertSee('Notifications')
            ->assertSee('Not available yet')
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('Open administration')
            ->assertDontSee('href="/ai', false)
            ->assertDontSee('href="/events', false);
    }

    public function test_ai_dashboard_navigation_requires_use_ai_permission(): void
    {
        $user = $this->userWithRole(null);

        $this->signedIn($user)->get('/app')->assertOk()
            ->assertDontSee('AI Assistant')
            ->assertDontSee('Recent AI conversations')
            ->assertSee('Prompt Library');
    }

    public function test_administration_dashboard_requires_access_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->signedIn($this->userWithRole())->get('/admin')->assertForbidden();
        $this->signedIn($this->userWithRole(Access::CONTENT_MANAGER))->get('/admin')->assertForbidden();
    }

    public function test_admin_navigation_and_metrics_follow_granular_permissions(): void
    {
        $eventDashboard = Role::create(['name' => 'Event Dashboard', 'guard_name' => 'web']);
        $eventDashboard->givePermissionTo(['access-admin', 'manage-events']);
        $user = $this->userWithRole($eventDashboard->name);

        $this->signedIn($user)->get('/admin')->assertOk()
            ->assertSee('Events')
            ->assertSee('Upcoming events')
            ->assertSee('Completed events')
            ->assertDontSee('Total users')
            ->assertDontSee('Users</a>', false)
            ->assertDontSee('Prompts')
            ->assertDontSee('AI Settings')
            ->assertDontSee('System Settings')
            ->assertDontSee('Recent activity');
    }

    public function test_super_administrator_sees_real_user_counts_and_sanitized_recent_activity(): void
    {
        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR, ['name' => 'Dashboard Administrator']);
        $active = $this->userWithRole(overrides: ['name' => 'Active Member']);
        $this->userWithRole(overrides: ['status' => 'disabled']);
        $this->userWithRole(overrides: ['status' => 'pending']);
        app(RecordAccountAudit::class)->handle($active, 'account.status_changed', ['reason' => 'private audit reason'], $super);

        $dashboard = app(AdminDashboardQuery::class)->get($super);
        $metrics = $dashboard['userMetrics']->keyBy('key');
        $this->assertSame(User::count(), $metrics['total_users']['value']);
        $this->assertSame(2, $metrics['active_users']['value']);
        $this->assertSame(1, $metrics['disabled_users']['value']);
        $this->assertSame('account.status_changed', $dashboard['recentActivity']->first()['action']);

        $this->signedIn($super)->get('/admin')->assertOk()
            ->assertSee('Total users')->assertSee('Active users')->assertSee('Disabled users')
            ->assertSee('Prompt count')->assertSee('AI conversations')->assertSee('Upcoming events')->assertSee('Completed events')
            ->assertSee('Account status changed')->assertSee('Account: Active Member')->assertSee('By Dashboard Administrator')
            ->assertDontSee('private audit reason')
            ->assertSee('System Settings');
    }

    public function test_dashboards_render_in_arabic_rtl(): void
    {
        $standard = $this->userWithRole(overrides: ['locale' => 'ar']);
        $this->signedIn($standard)->withSession(['locale' => 'ar'])->get('/app')->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)->assertSee('لوحة المعلومات')->assertSee('الوصول السريع');

        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR, ['locale' => 'ar']);
        $this->signedIn($super)->withSession(['locale' => 'ar'])->get('/admin')->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)->assertSee('المستخدمون النشطون')->assertSee('النشاط الأخير');
    }
}
