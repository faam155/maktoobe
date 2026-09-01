<?php

namespace Tests\Feature;

use App\Actions\PromptCategories\DeletePromptCategory;
use App\Models\PromptCategory;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\PromptCategorySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromptCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function signedIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    public function test_initial_bilingual_categories_are_seeded_idempotently_in_order(): void
    {
        $this->seed(PromptCategorySeeder::class);
        $this->seed(PromptCategorySeeder::class);

        $this->assertSame(11, PromptCategory::count());
        $this->assertSame(['writing', 'email', 'marketing', 'social-media', 'translation', 'design', 'events', 'reports', 'hr', 'corporate-communication', 'general'], PromptCategory::orderBy('display_order')->pluck('slug')->all());
        $this->assertSame('الكتابة', PromptCategory::where('slug', 'writing')->firstOrFail()->localizedName('ar'));
    }

    public function test_authorized_administrator_can_create_search_and_edit_a_category(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signedIn($admin)->post('/admin/prompt-categories', [
            'name_en' => 'Customer Care', 'name_ar' => 'خدمة العملاء', 'slug' => '',
            'description_en' => 'Customer communication.', 'description_ar' => 'التواصل مع العملاء.',
            'icon' => 'headphones', 'is_active' => '1',
        ])->assertRedirect();
        $category = PromptCategory::where('slug', 'customer-care')->firstOrFail();
        $this->assertSame($admin->id, $category->created_by);
        $this->get('/admin/prompt-categories?search=خدمة')->assertOk()->assertSee('Customer Care')->assertSee('خدمة العملاء');

        $this->put('/admin/prompt-categories/'.$category->id, [
            'name_en' => 'Customer Experience', 'name_ar' => 'تجربة العملاء', 'slug' => 'customer-experience',
            'description_en' => '', 'description_ar' => '', 'icon' => 'users', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('prompt_categories', ['id' => $category->id, 'slug' => 'customer-experience']);
        $this->assertDatabaseHas('prompt_category_translations', ['category_id' => $category->id, 'locale' => 'en', 'name' => 'Customer Experience']);
        $this->assertDatabaseHas('account_audits', ['actor_id' => $admin->id, 'action' => 'prompt_category.updated']);
    }

    public function test_validation_rejects_duplicate_slug_invalid_icon_and_missing_translation(): void
    {
        PromptCategory::factory()->create(['slug' => 'writing']);
        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signedIn($admin)->post('/admin/prompt-categories', [
            'name_en' => 'Writing', 'name_ar' => '', 'slug' => 'writing', 'icon' => '<svg>', 'is_active' => '1',
        ])->assertSessionHasErrors(['name_ar', 'slug', 'icon']);
    }

    public function test_status_and_order_changes_are_persisted(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $first = PromptCategory::factory()->create(['display_order' => 1]);
        $second = PromptCategory::factory()->create(['display_order' => 2]);
        $this->signedIn($admin)->patch('/admin/prompt-categories/'.$first->id.'/status', ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($first->fresh()->is_active);
        $this->patch('/admin/prompt-categories/'.$second->id.'/move', ['direction' => 'up'])->assertRedirect();
        $this->assertSame(1, $second->fresh()->display_order);
        $this->assertSame(2, $first->fresh()->display_order);
    }

    public function test_every_category_endpoint_enforces_manage_categories(): void
    {
        $category = PromptCategory::factory()->create();
        $standard = $this->user(Access::STANDARD_USER);
        $this->signedIn($standard)->get('/admin/prompt-categories')->assertForbidden();
        $this->post('/admin/prompt-categories', [])->assertForbidden();
        $this->put('/admin/prompt-categories/'.$category->id, [])->assertForbidden();
        $this->patch('/admin/prompt-categories/'.$category->id.'/status', ['is_active' => 0])->assertForbidden();
        $this->patch('/admin/prompt-categories/'.$category->id.'/move', ['direction' => 'up'])->assertForbidden();
        $this->delete('/admin/prompt-categories/'.$category->id)->assertForbidden();
    }

    public function test_unreferenced_category_can_be_deleted_and_foreign_key_reference_blocks_deletion(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $free = PromptCategory::factory()->create();
        $this->signedIn($admin)->delete('/admin/prompt-categories/'.$free->id)->assertRedirect(route('admin.prompt-categories.index'));
        $this->assertSoftDeleted('prompt_categories', ['id' => $free->id]);

        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('prompt_categories')->restrictOnDelete();
        });
        try {
            $used = PromptCategory::factory()->create();
            DB::table('prompts')->insert(['category_id' => $used->id]);
            $this->expectException(ValidationException::class);
            app(DeletePromptCategory::class)->handle($admin, $used);
        } finally {
            Schema::dropIfExists('prompts');
        }
    }

    public function test_arabic_category_interface_is_rtl_and_reusable_card_localizes_content(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $category = PromptCategory::factory()->create();
        DB::table('prompt_category_translations')->where('category_id', $category->id)->where('locale', 'en')->update(['name' => 'Legal']);
        DB::table('prompt_category_translations')->where('category_id', $category->id)->where('locale', 'ar')->update(['name' => 'قانوني', 'description' => 'محتوى قانوني.']);
        $category->load('translations');
        $this->signedIn($admin)->withSession(['locale' => 'ar'])->get('/admin/prompt-categories')
            ->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('قانوني');

        $html = $this->blade('<x-prompt-category-card :category="$category" />', compact('category'));
        $html->assertSee('قانوني')->assertSee('محتوى قانوني.');
    }
}
