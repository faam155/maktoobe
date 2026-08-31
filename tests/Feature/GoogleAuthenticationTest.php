<?php

namespace Tests\Feature;

use App\Actions\Identity\ResolveGoogleIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
    }

    private function identity(string $id = 'google-123', string $email = 'google@example.test', bool $verified = true): GoogleUser
    {
        return (new GoogleUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'email' => $email, 'name' => 'Google Member']);
    }

    private function fakeCallback(GoogleUser $identity): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }

    public function test_google_registration_creates_a_pending_passwordless_account_with_verified_email(): void
    {
        $this->fakeCallback($this->identity());
        $this->withSession(['google.started_at' => time()])->get('/auth/google/callback')->assertRedirect('/app');
        $user = User::firstOrFail();
        $this->assertSame('pending', $user->status->value);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_subject' => 'google-123']);
        $this->get('/app')->assertRedirect(route('account.pending'));
    }

    public function test_matching_email_is_never_silently_linked(): void
    {
        User::factory()->create(['email' => 'google@example.test']);
        $this->fakeCallback($this->identity());
        $this->withSession(['google.started_at' => time()])->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(ResolveGoogleIdentity::class)->handle($this->identity(verified: false));
    }

    public function test_authenticated_recent_owner_can_link_google(): void
    {
        $user = User::factory()->create();
        $this->fakeCallback($this->identity());
        $this->actingAs($user)->withSession(['auth.security_version' => 1, 'auth.confirmed_at' => time(), 'google.started_at' => time(), 'google.link_user' => $user->id])
            ->get('/auth/google/callback')->assertRedirect(route('account.security'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_subject' => 'google-123']);
    }

    public function test_disabled_google_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['status' => 'disabled']);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_subject' => 'google-123']);
        $this->fakeCallback($this->identity());
        $this->withSession(['google.started_at' => time()])->get('/auth/google/callback')->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_oauth_state_is_verified_by_real_socialite_before_any_provider_request(): void
    {
        $this->withSession(['google.started_at' => time(), 'state' => 'expected-state'])
            ->get('/auth/google/callback?state=wrong-state&code=fake-code')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_expired_link_intent_cannot_attach_an_identity(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.security_version' => 1, 'auth.confirmed_at' => time(), 'google.started_at' => time() - 601, 'google.link_user' => $user->id])
            ->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_missing_google_configuration_is_a_safe_unavailable_state(): void
    {
        config(['services.google.client_secret' => null]);
        $this->get('/auth/google')->assertRedirect(route('login'))->assertSessionHasErrors('google');
    }
}
