<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_users_hash_passwords_and_hide_credentials_when_serialized(): void
    {
        $user = User::factory()->unverified()->create(['password' => 'Test-only-secret-01']);
        $user->refresh();

        $this->assertTrue(Hash::check('Test-only-secret-01', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertNotNull(User::factory()->create()->email_verified_at);
    }

    public function test_mysql_rejects_duplicate_email_addresses_including_case_variants(): void
    {
        User::factory()->create(['email' => 'unique@example.test']);

        try {
            User::factory()->create(['email' => 'UNIQUE@example.test']);
            $this->fail('Email uniqueness must be enforced by MySQL.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }
    }
}
