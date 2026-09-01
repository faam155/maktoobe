<?php

namespace Tests\Feature;

use App\Contracts\AiProvider;
use App\Enums\AiRequestStatus;
use App\Enums\PromptSource;
use App\Enums\PromptVisibility;
use App\Exceptions\AiProviderException;
use App\Models\AiConversation;
use App\Models\AiRequest;
use App\Models\Prompt;
use App\Models\User;
use App\Services\Ai\OpenAiResponsesProvider;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixtures\FakeAiProvider;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->app->bind(AiProvider::class, FakeAiProvider::class);
        FakeAiProvider::reset();
        config(['ai.models' => ['gpt-test', 'gpt-other'], 'ai.default_model' => 'gpt-test', 'ai.role_models' => []]);
    }

    private function user(bool $withAi = true): User
    {
        $user = User::factory()->create();
        if ($withAi) {
            $user->assignRole(Access::STANDARD_USER);
        }

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['model' => 'gpt-test', 'content' => 'Help me prepare a concise project update.', 'client_operation_id' => (string) Str::uuid()], $overrides);
    }

    public function test_successful_response_stores_history_usage_and_safe_provider_metadata(): void
    {
        $user = $this->user();
        $this->signIn($user)->post('/app/assistant', $this->payload())->assertRedirect();
        $conversation = AiConversation::firstOrFail();
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame('Help me prepare a concise project update.', $conversation->title);
        $this->assertNotNull($conversation->last_message_at);
        $this->assertSame(['user', 'assistant'], $conversation->messages()->orderBy('id')->pluck('role')->all());
        $this->assertSame(['gpt-test', 'gpt-test'], $conversation->messages()->orderBy('id')->pluck('model')->all());
        $request = AiRequest::firstOrFail();
        $this->assertSame(AiRequestStatus::Completed, $request->status);
        $this->assertSame(20, $request->total_tokens);
        $this->assertSame('resp_test', $request->provider_request_id);
        $this->get('/app/assistant/'.$conversation->id)->assertOk()->assertSee('Mocked assistant response');
    }

    public function test_history_can_be_searched_sorted_archived_restored_and_paginated(): void
    {
        $user = $this->user();
        foreach (range(1, 17) as $number) {
            AiConversation::create(['user_id' => $user->id, 'title' => sprintf('History %02d', $number), 'model' => 'gpt-test', 'last_message_at' => now()->subMinutes($number)]);
        }
        $target = AiConversation::where('title', 'History 12')->firstOrFail();

        $this->signIn($user)->get('/app/assistant?search=History+12')->assertOk()->assertSee('History 12')->assertDontSee('History 11');
        $this->get('/app/assistant?sort=oldest')->assertOk()->assertSeeInOrder(['History 17', 'History 16']);
        $this->get('/app/assistant')->assertOk()->assertSee('History 01')->assertDontSee('History 17');
        $this->get('/app/assistant?page=2')->assertOk()->assertSee('History 16');

        $this->patch('/app/assistant/'.$target->id.'/archive', ['archived' => true])->assertRedirect('/app/assistant?status=archived');
        $this->get('/app/assistant')->assertDontSee('History 12');
        $this->get('/app/assistant?status=archived')->assertOk()->assertSee('History 12');
        $this->patch('/app/assistant/'.$target->id.'/archive', ['archived' => false])->assertRedirect('/app/assistant?status=active');
        $this->assertNull($target->fresh()->archived_at);
    }

    public function test_history_mutations_and_search_are_owner_scoped(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $private = AiConversation::create(['user_id' => $owner->id, 'title' => 'Owner secret history', 'model' => 'gpt-test']);

        $this->signIn($other)->get('/app/assistant?search=Owner+secret')->assertOk()->assertDontSee('Owner secret history');
        $this->get('/app/assistant/'.$private->id)->assertForbidden();
        $this->patch('/app/assistant/'.$private->id, ['title' => 'Stolen'])->assertForbidden();
        $this->patch('/app/assistant/'.$private->id.'/archive', ['archived' => true])->assertForbidden();
        $this->delete('/app/assistant/'.$private->id)->assertForbidden();
        $this->assertDatabaseHas('ai_conversations', ['id' => $private->id, 'title' => 'Owner secret history', 'archived_at' => null]);
    }

    public function test_conversation_view_paginates_messages_without_loading_the_complete_history(): void
    {
        $user = $this->user();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Long history', 'model' => 'gpt-test']);
        foreach (range(1, 65) as $number) {
            $conversation->messages()->create(['role' => $number % 2 ? 'user' : 'assistant', 'model' => 'gpt-test', 'content' => 'History message '.$number]);
        }

        $this->signIn($user)->get('/app/assistant/'.$conversation->id)->assertOk()->assertSee('History message 65')->assertDontSee('History message 1')->assertSee('Load older messages');
        $this->get('/app/assistant/'.$conversation->id.'?messages=3')->assertOk()->assertSee('History message 1')->assertDontSee('History message 65')->assertSee('Return to latest messages');
    }

    public function test_provider_errors_are_safe_and_retryable(): void
    {
        FakeAiProvider::$failure = 'timeout';
        $user = $this->user();
        $this->signIn($user)->post('/app/assistant', $this->payload())->assertRedirect();
        $conversation = AiConversation::firstOrFail();
        $failed = AiRequest::firstOrFail();
        $this->assertSame(AiRequestStatus::Failed, $failed->status);
        FakeAiProvider::$failure = null;
        $this->post('/app/assistant/'.$conversation->id.'/requests/'.$failed->id.'/retry')->assertRedirect();
        $this->assertSame(AiRequestStatus::Completed, AiRequest::latest('id')->first()->status);
        $this->assertDatabaseCount('ai_messages', 2);
    }

    public function test_permission_ownership_and_model_access_are_enforced(): void
    {
        $denied = $this->user(false);
        $this->signIn($denied)->get('/app/assistant')->assertForbidden();
        $owner = $this->user();
        $other = $this->user();
        $conversation = AiConversation::create(['user_id' => $owner->id, 'title' => 'Private AI', 'model' => 'gpt-test']);
        $this->signIn($other)->get('/app/assistant/'.$conversation->id)->assertForbidden();
        $this->signIn($owner)->post('/app/assistant/'.$conversation->id.'/messages', $this->payload(['model' => 'not-allowed']))->assertSessionHasErrors('model');
    }

    public function test_role_based_model_availability_is_enforced(): void
    {
        config(['ai.role_models' => [Access::STANDARD_USER => ['gpt-test']]]);
        $user = $this->user();
        $this->signIn($user)->get('/app/assistant/new')->assertOk()->assertSee('gpt-test')->assertDontSee('gpt-other');
        $this->post('/app/assistant', $this->payload(['model' => 'gpt-other']))->assertSessionHasErrors('model');
    }

    public function test_library_and_personal_prompts_are_reauthorized_and_snapshotted(): void
    {
        $user = $this->user();
        $owner = $this->user();
        $library = Prompt::factory()->published()->create(['owner_id' => $owner->id, 'content' => 'Approved prompt text.']);
        $personal = Prompt::factory()->create(['owner_id' => $user->id, 'source' => PromptSource::Personal, 'visibility' => PromptVisibility::Private, 'content' => 'My private prompt text.']);
        foreach ([$library, $personal] as $prompt) {
            $this->signIn($user)->post('/app/assistant', $this->payload(['prompt_id' => $prompt->id]))->assertRedirect();
            $request = AiRequest::latest('id')->firstOrFail();
            $this->assertSame($prompt->content, $request->prompt_snapshot);
            $this->assertDatabaseHas('prompt_uses', ['ai_request_id' => $request->id, 'kind' => 'ai']);
        }
        $foreign = Prompt::factory()->create(['owner_id' => $owner->id, 'source' => PromptSource::Personal]);
        $this->post('/app/assistant', $this->payload(['prompt_id' => $foreign->id]))->assertForbidden();
    }

    public function test_queued_request_can_be_cancelled_without_provider_execution(): void
    {
        Queue::fake();
        $user = $this->user();
        $this->signIn($user)->post('/app/assistant', $this->payload())->assertRedirect();
        $request = AiRequest::firstOrFail();
        $this->post('/app/assistant/'.$request->conversation_id.'/requests/'.$request->id.'/cancel')->assertRedirect();
        $this->assertSame(AiRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame([], FakeAiProvider::$calls);
    }

    public function test_message_endpoint_is_rate_limited_and_validated(): void
    {
        Queue::fake();
        $user = $this->user();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Rate test', 'model' => 'gpt-test']);
        $this->signIn($user)->post('/app/assistant/'.$conversation->id.'/messages', $this->payload(['content' => '']))->assertSessionHasErrors('content');
        for ($i = 0; $i < 9; $i++) {
            $this->post('/app/assistant/'.$conversation->id.'/messages', $this->payload())->assertRedirect();
        }
        $this->post('/app/assistant/'.$conversation->id.'/messages', $this->payload())->assertTooManyRequests();
    }

    public function test_openai_adapter_uses_backend_credentials_store_false_and_parses_usage(): void
    {
        config(['ai.openai.key' => 'server-only-test-key', 'ai.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fake(['api.openai.test/*' => Http::response(['id' => 'resp_http', 'output' => [[
            'content' => [['type' => 'output_text', 'text' => 'HTTP mocked response']],
        ]], 'usage' => ['input_tokens' => 4, 'output_tokens' => 3, 'total_tokens' => 7]])]);
        $result = app(OpenAiResponsesProvider::class)->generate([['role' => 'user', 'content' => 'Hello']], 'gpt-test', ['max_output_tokens' => 50, 'temperature' => null], 'safe-hash');
        $this->assertSame('HTTP mocked response', $result->content);
        $this->assertSame(7, $result->totalTokens);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer server-only-test-key') && $request['store'] === false && $request['safety_identifier'] === 'safe-hash');
    }

    public function test_openai_adapter_maps_provider_failure_without_storing_provider_body(): void
    {
        config(['ai.openai.key' => 'server-only-test-key', 'ai.openai.base_url' => 'https://api.openai.test/v1']);
        Http::fake(['api.openai.test/*' => Http::response(['error' => ['message' => 'sensitive provider detail']], 429)]);
        try {
            app(OpenAiResponsesProvider::class)->generate([['role' => 'user', 'content' => 'Hello']], 'gpt-test', ['max_output_tokens' => 50, 'temperature' => null], 'safe-hash');
            $this->fail('Expected provider exception.');
        } catch (AiProviderException $exception) {
            $this->assertSame('rate_limited', $exception->safeCode);
            $this->assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }
}
