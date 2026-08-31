<?php

namespace Tests\Feature;

use App\Actions\Administration\CreateFirstSuperAdministrator;
use App\Actions\Administration\CreateRole;
use App\Actions\Administration\SyncUserRoles;
use App\Actions\Identity\SetAccountStatus;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Notification::fake();
    }

    private function userWithRole(string $role = Access::STANDARD_USER, array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        $user->assignRole($role);

        return $user;
    }

    private function signedIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    public function test_seed_catalog_is_idempotent_and_has_expected_role_defaults(): void
    {
        $this->seed(AccessControlSeeder::class);
        $this->assertSame(5, Role::count());
        $this->assertSame(count(Access::PERMISSIONS), Permission::count());
        $this->assertEqualsCanonicalizing(Access::PERMISSIONS, Role::findByName(Access::SUPER_ADMINISTRATOR)->permissions()->pluck('name')->all());
        $this->assertTrue(Role::findByName(Access::ADMINISTRATOR)->hasPermissionTo('manage-users'));
        $this->assertFalse(Role::findByName(Access::ADMINISTRATOR)->hasPermissionTo('manage-permissions'));
        $this->assertSame(['use-ai'], Role::findByName(Access::STANDARD_USER)->permissions()->pluck('name')->all());
    }

    public function test_admin_routes_require_authentication_active_status_verification_and_permission(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->signedIn($this->userWithRole())->get('/admin')->assertForbidden();

        $unverified = $this->userWithRole(Access::ADMINISTRATOR, ['email_verified_at' => null]);
        $this->signedIn($unverified)->get('/admin')->assertRedirect(route('verification.notice'));

        $disabled = $this->userWithRole(Access::ADMINISTRATOR, ['status' => 'disabled']);
        $this->signedIn($disabled)->get('/admin')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_permissions_are_enforced_on_every_sensitive_endpoint(): void
    {
        $viewerRole = Role::create(['name' => 'Dashboard Viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo('access-admin');
        $viewer = $this->userWithRole($viewerRole->name);

        $this->signedIn($viewer)->get('/admin')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/permissions')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
    }

    public function test_super_can_search_filter_create_edit_assign_disable_and_delete_users(): void
    {
        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR);
        $this->signedIn($super)->post('/admin/users', [
            'name' => 'Managed Person', 'username' => 'managed.person', 'email' => 'managed@example.test',
            'phone' => '+968 9333 3333', 'password' => 'ManagedPassword123', 'password_confirmation' => 'ManagedPassword123',
            'status' => 'active', 'locale' => 'ar',
        ])->assertRedirect();
        $managed = User::where('email', 'managed@example.test')->firstOrFail();
        $this->assertTrue($managed->hasRole(Access::STANDARD_USER));
        $this->get('/admin/users?search=managed.person&status=active&role='.Role::findByName(Access::STANDARD_USER)->id)
            ->assertOk()->assertSee('Managed Person')->assertDontSee($super->email);

        $this->put('/admin/users/'.$managed->id, [
            'name' => 'Managed Updated', 'username' => 'managed.person', 'email' => 'changed@example.test',
            'phone' => '+96893333333', 'locale' => 'en',
        ])->assertRedirect();
        $this->assertNull($managed->fresh()->email_verified_at);

        $eventRole = Role::findByName(Access::EVENT_MANAGER);
        $this->put('/admin/users/'.$managed->id.'/roles', ['roles' => [$eventRole->id]])->assertRedirect();
        $this->assertTrue($managed->fresh()->hasExactRoles([$eventRole]));

        $this->patch('/admin/users/'.$managed->id.'/status', ['status' => 'disabled', 'reason' => 'Browser access revoked'])->assertRedirect();
        $this->assertSame(AccountStatus::Disabled, $managed->fresh()->status);
        $this->delete('/admin/users/'.$managed->id)->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $managed->id]);
    }

    public function test_role_assignment_revokes_existing_sessions(): void
    {
        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR);
        $target = $this->userWithRole();
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'ip_address' => null, 'user_agent' => null, 'payload' => 'private', 'last_activity' => time()]);
        $version = $target->security_version;

        app(SyncUserRoles::class)->handle($super, $target, [Role::findByName(Access::CONTENT_MANAGER)->id]);

        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertSame($version + 1, $target->fresh()->security_version);
        $this->assertTrue($target->fresh()->hasRole(Access::CONTENT_MANAGER));
    }

    public function test_administrator_cannot_manage_super_users_or_protected_role(): void
    {
        $administrator = $this->userWithRole(Access::ADMINISTRATOR);
        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR);
        $superRole = Role::findByName(Access::SUPER_ADMINISTRATOR);

        $this->signedIn($administrator)->get('/admin/users/'.$super->id.'/edit')->assertForbidden();
        $this->patch('/admin/users/'.$super->id.'/status', ['status' => 'disabled', 'reason' => 'Not authorized change'])->assertForbidden();
        $this->put('/admin/users/'.$super->id.'/roles', ['roles' => []])->assertForbidden();
        $this->get('/admin/roles/'.$superRole->id.'/edit')->assertForbidden();
        $this->put('/admin/roles/'.$superRole->id, ['name' => 'Changed', 'permissions' => []])->assertForbidden();
    }

    public function test_permission_and_role_escalation_are_rejected(): void
    {
        $delegatorRole = Role::create(['name' => 'Delegator', 'guard_name' => 'web']);
        $delegatorRole->givePermissionTo(['access-admin', 'manage-roles', 'manage-permissions']);
        $delegator = $this->userWithRole($delegatorRole->name);
        $target = $this->userWithRole();

        try {
            app(CreateRole::class)->handle($delegator, ['name' => 'Escalated', 'permissions' => ['delete-users']]);
            $this->fail('Unauthorized permission delegation must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permissions', $exception->errors());
        }
        try {
            app(SyncUserRoles::class)->handle($delegator, $target, [Role::findByName(Access::ADMINISTRATOR)->id]);
            $this->fail('Unauthorized role delegation must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('roles', $exception->errors());
        }
        $this->assertFalse(Role::where('name', 'Escalated')->exists());
        $this->assertTrue($target->hasRole(Access::STANDARD_USER));
    }

    public function test_super_can_create_and_edit_a_role_with_permissions_and_view_its_users(): void
    {
        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR);
        $this->signedIn($super)->post('/admin/roles', [
            'name' => 'Regional Coordinator', 'permissions' => ['manage-events', 'view-reports'],
        ])->assertRedirect();
        $role = Role::where('name', 'Regional Coordinator')->firstOrFail();
        $this->assertTrue($role->hasAllPermissions(['manage-events', 'view-reports']));
        $member = $this->userWithRole($role->name);

        $this->put('/admin/roles/'.$role->id, [
            'name' => 'Regional Lead', 'permissions' => ['manage-events', 'upload-event-files'],
        ])->assertRedirect();
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Regional Lead']);
        $this->get('/admin/roles/'.$role->id)->assertOk()->assertSee($member->name)->assertSee(__('admin.permissions.manage-events'));
    }

    public function test_last_active_super_is_protected_and_first_bootstrap_is_one_time(): void
    {
        $registered = User::factory()->create(['status' => 'pending', 'email_verified_at' => null]);
        $super = app(CreateFirstSuperAdministrator::class)->handle($registered->email);
        $this->assertTrue($super->hasRole(Access::SUPER_ADMINISTRATOR));
        $this->assertTrue($super->hasVerifiedEmail());

        try {
            app(SetAccountStatus::class)->handle($super, AccountStatus::Disabled, 'Attempt to remove final administrator');
            $this->fail('The last active Super Administrator must remain active.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $other = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(CreateFirstSuperAdministrator::class)->handle($other->email);
    }

    public function test_super_protection_allows_deactivation_when_another_active_super_exists(): void
    {
        $this->userWithRole(Access::SUPER_ADMINISTRATOR);
        $second = $this->userWithRole(Access::SUPER_ADMINISTRATOR);
        app(SetAccountStatus::class)->handle($second, AccountStatus::Disabled, 'Second administrator deactivated');
        $this->assertSame(AccountStatus::Disabled, $second->fresh()->status);
    }

    public function test_invalid_admin_inputs_and_arabic_rtl_errors_are_safe(): void
    {
        $super = $this->userWithRole(Access::SUPER_ADMINISTRATOR, ['locale' => 'ar']);
        $this->signedIn($super)->withSession(['locale' => 'ar'])->post('/admin/users', [
            'name' => '', 'username' => 'bad@email', 'email' => 'bad', 'password' => 'short',
            'password_confirmation' => 'different', 'status' => 'disabled', 'locale' => 'fr',
        ])->assertSessionHasErrors(['name', 'username', 'email', 'password', 'status', 'locale']);
        $this->get('/admin/users/create')->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    }
}
