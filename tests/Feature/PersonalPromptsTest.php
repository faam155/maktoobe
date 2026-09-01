<?php

namespace Tests\Feature;

use App\Enums\PromptSource;
use App\Enums\PromptStatus;
use App\Enums\PromptVisibility;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\PromptUse;
use App\Models\Tag;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalPromptsTest extends TestCase
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

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['title' => 'My Brief', 'slug' => '', 'description' => 'A private reusable brief.',
            'content' => 'Create a concise brief using the following private notes.', 'content_locale' => 'en',
            'category_id' => '', 'tags' => 'Private, Brief'], $overrides);
    }

    public function test_user_can_create_view_edit_duplicate_and_delete_a_personal_prompt(): void
    {
        $user = $this->user();
        $category = PromptCategory::factory()->create();
        $this->signIn($user)->post('/app/my-prompts', $this->payload(['category_id' => $category->id]))->assertRedirect();
        $prompt = Prompt::where('owner_id', $user->id)->firstOrFail();
        $this->assertSame(PromptSource::Personal, $prompt->source);
        $this->assertSame(PromptVisibility::Private, $prompt->visibility);
        $this->assertSame(PromptStatus::Draft, $prompt->status);
        $this->get('/app/my-prompts/'.$prompt->slug)->assertOk()->assertSee('My Brief');
        $this->put('/app/my-prompts/'.$prompt->slug, $this->payload(['title' => 'Updated Brief', 'slug' => $prompt->slug]))->assertRedirect();
        $this->assertSame(2, $prompt->fresh()->revision_number);
        $this->post('/app/my-prompts/'.$prompt->slug.'/duplicate')->assertRedirect();
        $this->assertDatabaseCount('prompts', 2);
        $this->delete('/app/my-prompts/'.$prompt->slug)->assertRedirect();
        $this->assertSoftDeleted('prompts', ['id' => $prompt->id]);
    }

    public function test_personal_prompt_ownership_isolated_even_from_administrators(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $admin = $this->user(Access::SUPER_ADMINISTRATOR);
        $prompt = Prompt::factory()->create(['owner_id' => $owner->id, 'source' => PromptSource::Personal, 'visibility' => PromptVisibility::Private, 'title' => 'Owner Secret Workspace Prompt']);

        foreach ([$other, $admin] as $actor) {
            $this->signIn($actor)->get('/app/my-prompts/'.$prompt->slug)->assertForbidden();
            $this->put('/app/my-prompts/'.$prompt->slug, $this->payload(['slug' => $prompt->slug]))->assertForbidden();
            $this->post('/app/my-prompts/'.$prompt->slug.'/duplicate')->assertForbidden();
            $this->delete('/app/my-prompts/'.$prompt->slug)->assertForbidden();
            $this->get('/app/my-prompts?search=Secret')->assertDontSee($prompt->title);
        }
    }

    public function test_personal_routes_cannot_mutate_library_prompts(): void
    {
        $admin = $this->user(Access::SUPER_ADMINISTRATOR);
        $library = Prompt::factory()->published()->create(['owner_id' => $admin->id]);
        $this->signIn($admin)->put('/app/my-prompts/'.$library->slug, $this->payload(['slug' => $library->slug]))->assertNotFound();
        $this->delete('/app/my-prompts/'.$library->slug)->assertNotFound();
        $this->post('/app/my-prompts/'.$library->slug.'/duplicate')->assertNotFound();
    }

    public function test_favorites_are_idempotent_removable_and_limited_to_visible_library_prompts(): void
    {
        $user = $this->user();
        $owner = $this->user(Access::ADMINISTRATOR);
        $category = PromptCategory::factory()->create();
        $public = Prompt::factory()->published()->create(['owner_id' => $owner->id, 'category_id' => $category->id, 'title' => 'Public Favorite']);
        $public->tags()->attach(Tag::create(['canonical_name' => 'favorite-tag', 'display_name' => 'Favorite Tag']));
        $private = Prompt::factory()->published(PromptVisibility::Private)->create(['owner_id' => $owner->id]);
        $personal = Prompt::factory()->create(['owner_id' => $user->id, 'source' => PromptSource::Personal]);
        $this->signIn($user)->post('/app/prompts/'.$public->slug.'/favorite')->assertRedirect();
        $this->post('/app/prompts/'.$public->slug.'/favorite')->assertRedirect();
        $this->assertDatabaseCount('prompt_favorites', 1);
        $this->post('/app/prompts/'.$private->slug.'/favorite')->assertForbidden();
        $this->post('/app/prompts/'.$personal->slug.'/favorite')->assertForbidden();
        $this->get('/app/my-prompts?section=favorites&category='.$category->id.'&tag=favorite-tag')->assertSee('Public Favorite')->assertSee('Favorite Tag')->assertDontSee($private->title);
        $this->delete('/app/prompts/'.$public->slug.'/favorite')->assertRedirect();
        $this->assertDatabaseCount('prompt_favorites', 0);
    }

    public function test_personal_and_favorite_search_category_and_tag_filters_are_owner_scoped(): void
    {
        $user = $this->user();
        $other = $this->user();
        $category = PromptCategory::factory()->create();
        $tag = Tag::create(['canonical_name' => 'strategy', 'display_name' => 'Strategy']);
        $mine = Prompt::factory()->create(['owner_id' => $user->id, 'source' => PromptSource::Personal, 'category_id' => $category->id, 'title' => 'Private Strategy']);
        $mine->tags()->attach($tag);
        Prompt::factory()->create(['owner_id' => $other->id, 'source' => PromptSource::Personal, 'title' => 'Other Private Strategy']);
        $this->signIn($user)->get('/app/my-prompts?search=Strategy&category='.$category->id.'&tag=strategy')
            ->assertOk()->assertSee('Private Strategy')->assertDontSee('Other Private Strategy');
    }

    public function test_recently_used_contains_only_current_authorized_prompts(): void
    {
        $user = $this->user();
        $owner = $this->user(Access::ADMINISTRATOR);
        $visible = Prompt::factory()->published()->create(['owner_id' => $owner->id, 'title' => 'Recent Visible']);
        $hidden = Prompt::factory()->published(PromptVisibility::Private)->create(['owner_id' => $owner->id, 'title' => 'Recent Hidden']);
        foreach ([$visible, $hidden] as $prompt) {
            PromptUse::create(['user_id' => $user->id, 'prompt_id' => $prompt->id, 'kind' => 'copy', 'client_operation_id' => (string) Str::uuid()]);
        }
        $this->signIn($user)->get('/app/my-prompts?section=recent')->assertSee('Recent Visible')->assertDontSee('Recent Hidden');
    }

    public function test_validation_rejects_invalid_personal_prompt_input(): void
    {
        $user = $this->user();
        $this->signIn($user)->post('/app/my-prompts', $this->payload(['title' => '', 'content' => 'short', 'tags' => '<script>']))
            ->assertSessionHasErrors(['title', 'content', 'tags.0']);
        $this->assertDatabaseCount('prompts', 0);
    }
}
