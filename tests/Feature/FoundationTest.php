<?php

namespace Tests\Feature;

use App\Livewire\Foundation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_loads_in_english_without_exposing_business_routes(): void
    {
        $this->get('/')->assertOk()->assertSee('lang="en" dir="ltr"', false)
            ->assertSee('Good work starts')->assertSeeLivewire(Foundation::class)
            ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Language', 'en');

        foreach (['/api/v1/users', '/storage/private.txt'] as $path) {
            $this->get($path)->assertNotFound()->assertDontSee('password');
        }
    }

    public function test_arabic_preference_changes_the_document_and_survives_requests(): void
    {
        Livewire::test(Foundation::class)->set('locale', 'ar')->call('saveLocale')
            ->assertHasNoErrors()->assertRedirect(route('foundation'));

        $this->assertSame('ar', session('locale'));
        $this->get('/')->assertOk()->assertSee('lang="ar" dir="rtl"', false)
            ->assertSee('العمل الجيد يبدأ')->assertHeader('Content-Language', 'ar');
        $this->get('/')->assertSee('lang="ar" dir="rtl"', false);

        Livewire::test(Foundation::class)->set('locale', 'en')->call('saveLocale');
        $this->get('/')->assertSee('lang="en" dir="ltr"', false);
    }

    #[DataProvider('invalidLocales')]
    public function test_invalid_preferences_do_not_replace_the_saved_locale(string $value): void
    {
        session()->put('locale', 'ar');
        app()->setLocale('ar');

        Livewire::test(Foundation::class)->set('locale', $value)->call('saveLocale')->assertHasErrors('locale');

        $this->assertSame('ar', session('locale'));
    }

    public static function invalidLocales(): array
    {
        return [[''], ['fr'], ['../../.env'], ['<script>alert(1)</script>']];
    }

    public function test_corrupt_session_locale_falls_back_without_being_rendered(): void
    {
        $this->withSession(['locale' => ['ar']])->get('/')->assertOk()->assertSee('lang="en" dir="ltr"', false);
    }

    public function test_language_changes_are_rate_limited_per_session(): void
    {
        session()->start();
        $key = 'locale-preference:'.hash('sha256', session()->getId());
        RateLimiter::increment($key, decaySeconds: 60, amount: 20);

        Livewire::test(Foundation::class)->set('locale', 'ar')->call('saveLocale')
            ->assertHasErrors('locale')->assertSee('Please try again in a minute.');

        $this->assertNull(session('locale'));
    }

    public function test_livewire_update_rejects_a_cross_site_request_without_csrf(): void
    {
        $this->app->instance('env', 'local');

        $this->post(Livewire::getUpdateUri(), [], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
    }

    public function test_arabic_not_found_page_has_a_safe_return_link(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/missing-page')->assertNotFound()
            ->assertSee('dir="rtl"', false)->assertSee('العودة إلى البداية');
    }

    public function test_framework_tables_use_the_isolated_mysql_database_without_seeded_accounts(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('maktoobe_test', DB::connection()->getDatabaseName());
        $this->assertSame('utf8mb4_unicode_ci', DB::selectOne('SELECT @@collation_database AS value')->value);

        foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->seed();
        $this->assertDatabaseCount('users', 0);
        $this->assertFalse(Schema::hasTable('prompts'));
        $this->assertFalse(Schema::hasTable('events'));
    }

    public function test_both_locales_have_matching_interface_keys(): void
    {
        foreach (['foundation', 'validation', 'errors', 'auth', 'passwords', 'admin'] as $file) {
            $this->assertSame(array_keys(require lang_path("en/$file.php")), array_keys(require lang_path("ar/$file.php")));
        }
    }

    public function test_test_credentials_cannot_read_the_development_database(): void
    {
        try {
            DB::select('SELECT COUNT(*) FROM maktoobe.users');
            $this->fail('The test user must not have access to the development database.');
        } catch (QueryException $exception) {
            $this->assertSame(1142, $exception->errorInfo[1]);
        }
    }
}
