<?php

namespace Tests\Feature;

use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\Tag;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromptLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    private function user(string $role = Access::STANDARD_USER): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function signedIn(User $user, bool $recent = true): static
    {
        $session = ['auth.security_version' => $user->security_version];
        if ($recent) {
            $session['auth.confirmed_at'] = time();
        }

        return $this->actingAs($user)->withSession($session);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Professional Email', 'slug' => '', 'description' => 'Draft a clear professional email.',
            'content' => 'Write a professional email about the following subject and desired outcome.',
            'content_locale' => 'en', 'category_id' => '', 'visibility' => 'all_users',
            'tags' => 'Email, Writing', 'user_ids' => [], 'role_ids' => [],
        ], $overrides);
    }

    public function test_administrator_can_create_edit_preview_and_publish_a_library_prompt(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $category = PromptCategory::factory()->create();
        $this->signedIn($admin)->post('/admin/prompts', $this->payload(['category_id' => $category->id]))->assertRedirect();
        $prompt = Prompt::where('slug', 'professional-email')->firstOrFail();
        $this->assertSame(PromptStatus::Draft, $prompt->status);
        $this->assertSame(['email', 'writing'], $prompt->tags()->orderBy('canonical_name')->pluck('canonical_name')->all());
        $this->get('/admin/prompts/'.$prompt->slug)->assertOk()->assertSee($prompt->content);

        $this->put('/admin/prompts/'.$prompt->slug, $this->payload(['title' => 'Executive Email', 'slug' => 'executive-email']))->assertRedirect();
        $prompt = $prompt->fresh();
        $this->assertSame(2, $prompt->revision_number);
        $this->patch('/admin/prompts/'.$prompt->slug.'/status', ['status' => 'published'])->assertRedirect();
        $this->assertSame(PromptStatus::Published, $prompt->fresh()->status);
        $this->assertNotNull($prompt->fresh()->published_at);
    }

    public function test_publishing_requires_publish_permission_and_valid_audience(): void
    {
        $role = Role::create(['name' => 'Prompt Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(['access-admin', 'manage-prompts']);
        $editor = $this->user($role->name);
        $prompt = Prompt::factory()->create(['owner_id' => $editor->id, 'visibility' => PromptVisibility::SelectedUsers]);
        $this->signedIn($editor)->patch('/admin/prompts/'.$prompt->slug.'/status', ['status' => 'published'])->assertForbidden();

        $admin = $this->user(Access::ADMINISTRATOR);
        $prompt->owner_id = $admin->id;
        $prompt->save();
        $this->signedIn($admin)->patch('/admin/prompts/'.$prompt->slug.'/status', ['status' => 'published'])->assertSessionHasErrors('user_ids');
    }

    public function test_visibility_scope_prevents_cross_user_and_cross_role_disclosure(): void
    {
        $owner = $this->user(Access::ADMINISTRATOR);
        $member = $this->user();
        $other = $this->user();
        $eventManager = $this->user(Access::EVENT_MANAGER);
        $all = Prompt::factory()->published()->create(['owner_id' => $owner->id, 'title' => 'All Visible']);
        $private = Prompt::factory()->published(PromptVisibility::Private)->create(['owner_id' => $owner->id, 'title' => 'Owner Secret']);
        $private->tags()->attach(Tag::create(['canonical_name' => 'secret-tag', 'display_name' => 'Secret Tag']));
        $selected = Prompt::factory()->published(PromptVisibility::SelectedUsers)->create(['owner_id' => $owner->id, 'title' => 'Selected User']);
        $selected->allowedUsers()->attach($member->id, ['granted_by' => $owner->id, 'created_at' => now()]);
        $rolePrompt = Prompt::factory()->published(PromptVisibility::SelectedRoles)->create(['owner_id' => $owner->id, 'title' => 'Event Role']);
        $rolePrompt->allowedRoles()->attach(Role::findByName(Access::EVENT_MANAGER)->id, ['granted_by' => $owner->id, 'created_at' => now()]);

        $this->signedIn($member)->get('/app/prompts')->assertSee('All Visible')->assertSee('Selected User')->assertDontSee('Owner Secret')->assertDontSee('Event Role')->assertDontSee('Secret Tag');
        $this->get('/app/prompts/'.$private->slug)->assertForbidden();
        $this->signedIn($other)->get('/app/prompts')->assertSee('All Visible')->assertDontSee('Selected User');
        $this->signedIn($eventManager)->get('/app/prompts')->assertSee('Event Role');
        $this->get('/app/prompts/'.$all->slug)->assertOk();
    }

    public function test_drafts_archives_and_inactive_category_prompts_never_appear_to_users(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $member = $this->user();
        $draft = Prompt::factory()->create(['owner_id' => $admin->id, 'title' => 'Hidden Draft']);
        $archived = Prompt::factory()->published()->create(['owner_id' => $admin->id, 'title' => 'Hidden Archive', 'status' => PromptStatus::Archived]);
        $category = PromptCategory::factory()->create(['is_active' => false]);
        Prompt::factory()->published()->create(['owner_id' => $admin->id, 'category_id' => $category->id, 'title' => 'Inactive Category']);
        $this->signedIn($member)->get('/app/prompts')->assertDontSee($draft->title)->assertDontSee($archived->title)->assertDontSee('Inactive Category');
        $this->get('/app/prompts/'.$draft->slug)->assertForbidden();
        $this->signedIn($admin)->get('/admin/prompts/'.$draft->slug)->assertOk();
    }

    public function test_search_category_tag_and_sort_apply_after_visibility(): void
    {
        $member = $this->user();
        $owner = $this->user(Access::ADMINISTRATOR);
        $category = PromptCategory::factory()->create();
        $match = Prompt::factory()->published()->create(['owner_id' => $owner->id, 'category_id' => $category->id, 'title' => 'Quarterly Report', 'content' => 'Summarize quarterly financial outcomes.']);
        $tag = Tag::create(['canonical_name' => 'reports', 'display_name' => 'Reports']);
        $match->tags()->attach($tag);
        Prompt::factory()->published()->create(['owner_id' => $owner->id, 'title' => 'Marketing Plan']);
        Prompt::factory()->published(PromptVisibility::Private)->create(['owner_id' => $owner->id, 'title' => 'Secret Quarterly Report']);

        $this->signedIn($member)->get('/app/prompts?search=quarterly&category='.$category->id.'&tag=reports&sort=title')
            ->assertOk()->assertSee('Quarterly Report')->assertDontSee('Marketing Plan')->assertDontSee('Secret Quarterly Report');
    }

    public function test_copy_tracking_is_authorized_and_idempotent(): void
    {
        $member = $this->user();
        $other = $this->user();
        $public = Prompt::factory()->published()->create();
        $private = Prompt::factory()->published(PromptVisibility::Private)->create();
        $operation = (string) Str::uuid();
        $this->signedIn($member)->postJson('/app/prompts/'.$public->slug.'/copy', ['client_operation_id' => $operation])->assertOk();
        $this->postJson('/app/prompts/'.$public->slug.'/copy', ['client_operation_id' => $operation])->assertOk();
        $this->assertDatabaseCount('prompt_uses', 1);
        $this->signedIn($other)->postJson('/app/prompts/'.$private->slug.'/copy', ['client_operation_id' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_duplicate_is_private_draft_and_delete_is_soft(): void
    {
        $admin = $this->user(Access::ADMINISTRATOR);
        $prompt = Prompt::factory()->published()->create(['owner_id' => $admin->id]);
        $tag = Tag::create(['canonical_name' => 'writing', 'display_name' => 'Writing']);
        $prompt->tags()->attach($tag);
        $this->signedIn($admin)->post('/admin/prompts/'.$prompt->slug.'/duplicate')->assertRedirect();
        $copy = Prompt::where('id', '!=', $prompt->id)->firstOrFail();
        $this->assertSame(PromptStatus::Draft, $copy->status);
        $this->assertSame(PromptVisibility::Private, $copy->visibility);
        $this->assertCount(1, $copy->tags);
        $this->delete('/admin/prompts/'.$copy->slug)->assertRedirect();
        $this->assertSoftDeleted('prompts', ['id' => $copy->id]);
    }

    public function test_validation_and_server_authorization_cover_every_mutation(): void
    {
        $standard = $this->user();
        $prompt = Prompt::factory()->create();
        $this->signedIn($standard)->get('/admin/prompts')->assertForbidden();
        $this->post('/admin/prompts', $this->payload())->assertForbidden();
        $this->put('/admin/prompts/'.$prompt->slug, $this->payload())->assertForbidden();
        $this->patch('/admin/prompts/'.$prompt->slug.'/status', ['status' => 'published'])->assertForbidden();
        $this->post('/admin/prompts/'.$prompt->slug.'/duplicate')->assertForbidden();
        $this->delete('/admin/prompts/'.$prompt->slug)->assertForbidden();

        $admin = $this->user(Access::ADMINISTRATOR);
        $this->signedIn($admin)->post('/admin/prompts', $this->payload(['title' => '', 'content' => 'short', 'slug' => 'Bad Slug', 'visibility' => 'selected_roles']))
            ->assertSessionHasErrors(['title', 'content', 'slug']);
        $this->post('/admin/prompts', $this->payload(['visibility' => 'selected_roles']))->assertSessionHasErrors('role_ids');
    }

    public function test_arabic_library_is_rtl_and_content_is_escaped(): void
    {
        $member = $this->user();
        $prompt = Prompt::factory()->published()->create(['title' => 'موجّه عربي', 'description' => '<script>alert(1)</script>', 'content' => '<img src=x onerror=alert(1)>']);
        $this->signedIn($member)->withSession(['locale' => 'ar'])->get('/app/prompts/'.$prompt->slug)
            ->assertOk()->assertSee('lang="ar" dir="rtl"', false)->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)->assertDontSee('<img src=x', false);
    }
}
