<?php

namespace Tests\Feature;

use App\Actions\Identity\VerifyOtp;
use App\Contracts\SmsGateway;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\Identity\LocalInbox;
use App\Services\Identity\LocalSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\FakeSmsGateway;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
    }

    private function user(): User
    {
        return User::factory()->create(['phone_e164' => '+96891234567', 'phone_verified_at' => now()]);
    }

    private function issue(): OtpChallenge
    {
        $this->post('/otp', ['phone' => '+968 9123 4567'])->assertRedirect(route('otp.verify'));
        $this->withCookie(config('session.cookie'), session()->getId());

        return OtpChallenge::findOrFail(session('otp.login'));
    }

    public function test_code_is_hashed_expiring_and_single_use_then_authenticates(): void
    {
        $user = $this->user();
        $challenge = $this->issue();
        $code = $this->sms->sent[0]['code'];
        $this->assertNotSame($code, $challenge->code_digest);
        $this->assertSame(64, strlen($challenge->code_digest));
        $this->assertTrue($challenge->expires_at->isFuture());
        $session = session()->getId();
        $this->post('/otp/verify', compact('code'))->assertRedirect('/app');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($challenge->fresh()->consumed_at);
        $this->assertNull(app(VerifyOtp::class)->handle($challenge->id, $code, $session, 'login'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->user();
        $this->issue();
        $code = $this->sms->sent[0]['code'];
        $this->travel(6)->minutes();
        $this->post('/otp/verify', compact('code'))->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_wrong_codes_exhaust_the_attempt_budget_even_if_the_last_code_is_correct(): void
    {
        $this->user();
        $challenge = $this->issue();
        $code = $this->sms->sent[0]['code'];
        for ($i = 0; $i < 5; $i++) {
            $this->post('/otp/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/otp/verify', compact('code'))->assertSessionHasErrors('code');
        $this->assertSame(5, $challenge->fresh()->attempts);
        $this->assertGuest();
    }

    public function test_code_cannot_be_verified_in_another_session_or_for_another_purpose(): void
    {
        $this->user();
        $challenge = $this->issue();
        $code = $this->sms->sent[0]['code'];
        $verify = app(VerifyOtp::class);
        $this->assertNull($verify->handle($challenge->id, $code, 'other-session', 'login'));
        $this->assertNull($verify->handle($challenge->id, $code, session()->getId(), 'enroll'));
        $this->assertSame(0, $challenge->fresh()->attempts);
    }

    public function test_resend_cooldown_and_new_code_invalidate_old_challenges(): void
    {
        $this->user();
        $old = $this->issue();
        $this->post('/otp', ['phone' => '+96891234567'])->assertSessionHasErrors('phone');
        $this->travel(61)->seconds();
        $new = $this->issue();
        $this->assertNotSame($old->id, $new->id);
        $this->assertNotNull($old->fresh()->invalidated_at);
    }

    public function test_unknown_disabled_pending_and_unverified_numbers_do_not_receive_codes(): void
    {
        foreach (['disabled', 'pending', 'active'] as $status) {
            $user = User::factory()->create(['status' => $status, 'phone_e164' => '+9689'.random_int(1000000, 9999999), 'phone_verified_at' => $status === 'active' ? null : now()]);
            $this->post('/otp', ['phone' => $user->phone_e164])->assertRedirect(route('otp.verify'));
        }
        $this->post('/otp', ['phone' => '+96898888888'])->assertRedirect(route('otp.verify'));
        $this->assertCount(0, $this->sms->sent);
    }

    public function test_disabling_after_issuance_prevents_otp_login(): void
    {
        $user = $this->user();
        $this->issue();
        $code = $this->sms->sent[0]['code'];
        $user->forceFill(['status' => 'disabled'])->save();
        $this->post('/otp/verify', compact('code'))->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_authenticated_phone_enrollment_is_required_before_otp_login(): void
    {
        $user = User::factory()->create(['phone_e164' => '+96891234567', 'phone_verified_at' => null]);
        $this->actingAs($user)->withSession(['auth.security_version' => 1, 'auth.confirmed_at' => time()])->post('/account/phone')->assertRedirect(route('phone.verify'));
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/account/phone/verify', ['code' => $this->sms->sent[0]['code']])->assertRedirect(route('account.security'));
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_delivery_failure_invalidates_the_challenge_without_leaking_details(): void
    {
        $this->user();
        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function sendOtp(string $phone, string $code): void
            {
                throw new \RuntimeException('Sensitive provider data');
            }
        });
        $challenge = $this->issue();
        $this->assertNotNull($challenge->invalidated_at);
        $this->assertDatabaseHas('account_audits', ['action' => 'otp.delivery_failed']);
    }

    public function test_local_sms_is_encrypted_and_never_public(): void
    {
        Storage::fake('local');
        app(LocalSmsGateway::class)->sendOtp('+96891234567', '123456');
        $file = Storage::disk('local')->files('auth-inbox/testing')[0];
        $this->assertStringNotContainsString('123456', Storage::disk('local')->get($file));
        $messages = app(LocalInbox::class)->messages();
        $this->assertSame('123456', $messages[0]['code']);
        $this->get('/storage/'.$file)->assertNotFound();
    }
}
