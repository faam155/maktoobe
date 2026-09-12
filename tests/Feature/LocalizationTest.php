<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Authorization\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_catalog_labels_resolve_in_both_locales(): void
    {
        foreach (Access::PERMISSIONS as $permission) {
            foreach (['en', 'ar'] as $locale) {
                $this->assertTrue(Lang::hasForLocale('admin.permissions.'.$permission, $locale), $permission.' / '.$locale);
            }
        }
    }

    public function test_english_and_arabic_catalogs_have_identical_recursive_keys(): void
    {
        $englishFiles = collect(glob(lang_path('en/*.php')))->map(fn (string $path) => basename($path))->sort()->values();
        $arabicFiles = collect(glob(lang_path('ar/*.php')))->map(fn (string $path) => basename($path))->sort()->values();

        $this->assertSame($englishFiles->all(), $arabicFiles->all());

        foreach ($englishFiles as $file) {
            $english = Arr::dot(require lang_path("en/{$file}"));
            $arabic = Arr::dot(require lang_path("ar/{$file}"));

            $this->assertSame(array_keys($english), array_keys($arabic), "Translation keys differ in {$file}.");
            $this->assertNotContains('', array_values($english), "English contains an empty translation in {$file}.");
            $this->assertNotContains('', array_values($arabic), "Arabic contains an empty translation in {$file}.");
        }
    }

    public function test_static_translation_references_exist_in_both_catalogs(): void
    {
        $missing = [];

        foreach ([app_path(), resource_path('views')] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($files as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'blade.php'], true)) {
                    continue;
                }

                preg_match_all('/(?:__|trans_choice)\(\s*[\'\"]([^\'\"]+)[\'\"]/', file_get_contents($file->getPathname()), $matches);
                foreach (array_unique($matches[1]) as $key) {
                    if (str_ends_with($key, '.')) {
                        continue;
                    }
                    foreach (['en', 'ar'] as $locale) {
                        if (! Lang::hasForLocale($key, $locale)) {
                            $missing[] = "{$locale}:{$key} ({$file->getFilename()})";
                        }
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Missing translations: '.implode(', ', $missing));
    }

    public function test_authenticated_language_choice_is_saved_to_the_account(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version])
            ->from('/app')->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect('/app');

        $this->assertSame('ar', session('locale'));
        $this->assertSame('ar', $user->fresh()->locale);
        session()->forget('locale');
        $this->get('/')->assertSee('lang="ar" dir="rtl"', false);
    }

    public function test_guest_language_choice_is_adopted_by_the_account_at_sign_in(): void
    {
        $user = User::factory()->create(['locale' => 'en', 'password' => 'password']);

        $this->withSession(['locale' => 'ar'])->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('ar', $user->fresh()->locale);
        $this->get(route('account.home'))->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    }

    public function test_validation_errors_are_localized_in_arabic(): void
    {
        $response = $this->withSession(['locale' => 'ar'])->from(route('register'))->post(route('register.store'), [
            'name' => '', 'username' => '', 'email' => 'not-an-email', 'phone' => 'bad', 'password' => 'short',
        ]);

        $response->assertRedirect(route('register'))->assertSessionHasErrors(['name', 'username', 'email', 'phone', 'password']);
        $errors = session('errors')->all();

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('مطلوب', implode(' ', $errors));
        $this->assertStringNotContainsString('The ', implode(' ', $errors));
    }
}
