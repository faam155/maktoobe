<?php

namespace Tests\Feature;

use App\Actions\Identity\SetAccountStatus;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Notifications\ResetAccountPassword;
use App\Notifications\VerifyAccountEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function registration(array $overrides = []): array
    {
        return array_replace(['name' => 'New Member', 'username' => 'New.Member', 'email' => 'NEW@example.test', 'phone' => '٠٠٩٦٨ ٩١٢٣ ٤٥٦٧', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123'], $overrides);
    }

    private function signedIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    public function test_registration_normalizes_identifiers_and_never_accepts_privileged_state(): void
    {
        $this->post('/register', $this->registration(['status' => 'active', 'email_verified_at' => now(), 'security_version' => 99]))->assertRedirect('/app');
        $user = User::firstOrFail();
        $this->assertSame('new.member', $user->username);
        $this->assertSame('new@example.test', $user->email);
        $this->assertSame('+96891234567', $user->phone_e164);
        $this->assertSame(AccountStatus::Pending, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertTrue(Hash::check('SecurePassword123', $user->password));
        Notification::assertSentTo($user, VerifyAccountEmail::class);
        $this->get('/app')->assertRedirect(route('account.pending'));
    }

    public function test_registration_validates_unique_identifiers_and_password_confirmation(): void
    {
        User::factory()->create(['email' => 'new@example.test', 'username' => 'new.member']);
        $this->post('/register', $this->registration(['password_confirmation' => 'different']))->assertSessionHasErrors(['email', 'username', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_ambiguous_username_national_phone_and_short_password(): void
    {
        $this->post('/register', $this->registration(['username' => 'email@example.test', 'phone' => '91234567', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors(['username', 'phone', 'password']);
    }

    public function test_email_and_username_login_are_case_insensitive_and_regenerate_the_session(): void
    {
        $user = User::factory()->create(['username' => 'member', 'email' => 'member@example.test', 'password' => 'SecurePassword123']);
        $this->get('/login');
        $old = session()->getId();
        $this->post('/login', ['login' => 'MEMBER@example.test', 'password' => 'SecurePassword123', 'remember' => 1])->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($old, session()->getId());
        $this->assertEquals($user->security_version, session('auth.security_version'));
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['login' => 'MEMBER', 'password' => 'SecurePassword123'])->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_issues_a_recaller_cookie(): void
    {
        User::factory()->create(['username' => 'remember', 'password' => 'SecurePassword123']);
        $this->post('/login', ['login' => 'remember', 'password' => 'SecurePassword123', 'remember' => 1])->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_invalid_and_disabled_credentials_return_the_same_failure(): void
    {
        User::factory()->create(['username' => 'disabled', 'status' => 'disabled', 'password' => 'SecurePassword123']);
        foreach (['unknown', 'disabled'] as $login) {
            $this->post('/login', ['login' => $login, 'password' => 'SecurePassword123'])->assertSessionHasErrors(['login' => __('auth.failed')]);
            $this->assertGuest();
        }
    }

    public function test_login_throttles_repeated_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'missing', 'password' => 'not-correct'])->assertStatus(302);
        }
        $this->post('/login', ['login' => 'missing', 'password' => 'not-correct'])->assertStatus(429);
    }

    public function test_verified_active_status_is_required_for_the_workspace(): void
    {
        $user = User::factory()->unverified()->create();
        $this->signedIn($user)->get('/app')->assertRedirect(route('verification.notice'));
        $user->markEmailAsVerified();
        $this->get('/app')->assertOk();
    }

    public function test_disabling_an_account_revokes_sessions_and_rejects_existing_authentication(): void
    {
        $user = User::factory()->create();
        $this->signedIn($user)->get('/app')->assertOk();
        app(SetAccountStatus::class)->handle($user, AccountStatus::Disabled, 'Security test disable');
        $this->get('/app')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('account_audits', ['user_id' => $user->id, 'action' => 'account.status_changed']);
    }

    public function test_logout_invalidates_the_session_and_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->signedIn($user)->get('/app');
        $token = session()->token();
        $id = session()->getId();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertNotSame($id, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->get('/app')->assertRedirect(route('login'));
    }

    public function test_password_recovery_does_not_disclose_unknown_or_disabled_accounts(): void
    {
        $user = User::factory()->create(['email' => 'active@example.test']);
        User::factory()->create(['email' => 'disabled@example.test', 'status' => 'disabled']);
        foreach (['active@example.test', 'disabled@example.test', 'unknown@example.test'] as $email) {
            $this->post('/forgot-password', compact('email'))->assertSessionHas('status', __('auth.reset_sent'));
        }
        Notification::assertSentTo($user, ResetAccountPassword::class);
        Notification::assertCount(1);
    }

    public function test_password_reset_changes_password_and_revokes_old_credentials(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123']);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertSame(2, $user->fresh()->security_version);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'ReplayPassword123', 'password_confirmation' => 'ReplayPassword123'])->assertSessionHasErrors('email');
    }

    public function test_invalid_reset_token_and_disabled_account_cannot_change_password(): void
    {
        $user = User::factory()->create(['status' => 'disabled', 'password' => 'OldPassword123']);
        foreach (['invalid', Password::createToken($user)] as $token) {
            $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertSessionHasErrors('email');
        }
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_signed_email_verification_rejects_tampering_and_verifies_the_owner(): void
    {
        $user = User::factory()->unverified()->create();
        $this->signedIn($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url.'&tamper=1')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get($url)->assertRedirect('/app');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_user_cannot_verify_someone_elses_email(): void
    {
        $owner = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $owner->id, 'hash' => sha1($owner->email)]);
        $this->signedIn($other)->get($url)->assertForbidden();
    }

    public function test_session_list_hides_tokens_and_revocation_keeps_the_current_session(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'private-session-id', 'user_id' => $user->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Test browser', 'payload' => 'private-payload', 'last_activity' => time()]);
        $this->signedIn($user)->get('/account/security')->assertOk()->assertDontSee('private-session-id')->assertDontSee('private-payload');
        $this->post('/account/sessions/revoke')->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'private-session-id']);
        $this->assertSame(2, session('auth.security_version'));
        $this->get('/app')->assertOk();
    }

    public function test_sensitive_security_changes_require_recent_authentication(): void
    {
        $user = User::factory()->create();
        $this->signedIn($user)->withSession(['auth.confirmed_at' => time() - 1000])->post('/account/sessions/revoke')->assertRedirect(route('password.confirm'));
    }

    public function test_auth_forms_render_in_arabic_and_cross_site_login_requires_csrf(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/otp'] as $path) {
            $this->withSession(['locale' => 'ar'])->get($path)->assertOk()->assertSee('lang="ar" dir="rtl"', false);
        }
        $this->app->instance('env', 'local');
        $this->post('/login', ['login' => 'no', 'password' => 'no'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
    }
}
