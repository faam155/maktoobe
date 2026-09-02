<?php

namespace Tests\Feature;

use App\Actions\Events\GenerateEventCommunication;
use App\Contracts\AiProvider;
use App\Data\AiGenerationResult;
use App\Jobs\GenerateEventCommunicationContent;
use App\Models\BrandGuideline;
use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\EventCommunicationGeneration;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Fixtures\FakeAiProvider;
use Tests\TestCase;

class EventCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        Queue::fake();
        FakeAiProvider::reset();
        $this->app->bind(AiProvider::class, FakeAiProvider::class);
        config(['ai.models' => ['gpt-test'], 'ai.default_model' => 'gpt-test', 'ai.role_models' => []]);
        $this->manager = User::factory()->create();
        $this->manager->assignRole(Access::EVENT_MANAGER);
        $this->manager->givePermissionTo('use-ai');
        RateLimiter::clear('event-communication-ai:'.$this->manager->id);
        $this->event = Event::factory()->create(['visibility' => 'private', 'title' => 'Private conference']);
        $this->signIn($this->manager);
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['auth.security_version' => $user->security_version, 'auth.confirmed_at' => time()]);
    }

    private function url(string $suffix = ''): string
    {
        return '/app/events/'.$this->event->slug.'/communications'.$suffix;
    }

    private function manual(array $extra = []): array
    {
        return $extra + ['type' => 'internal_email', 'language' => 'en', 'title' => 'Invitation', 'content' => 'Please join us.', 'status' => 'draft', 'revision_number' => 0];
    }

    private function ai(array $extra = []): array
    {
        return $extra + ['type' => 'internal_email', 'language' => 'en', 'operation' => 'generate', 'model' => 'gpt-test', 'instructions' => 'Be concise', 'client_operation_id' => (string) Str::uuid(), 'revision_number' => 0];
    }

    private function generate(array $extra = []): EventCommunicationGeneration
    {
        return app(GenerateEventCommunication::class)->handle($this->manager, $this->event, $this->ai($extra));
    }

    private function runJob(EventCommunicationGeneration $generation): void
    {
        (new GenerateEventCommunicationContent($generation->id))->handle(app(AiProvider::class));
    }

    public function test_manual_crud_preserves_six_language_slots_and_revision_history(): void
    {
        foreach (EventCommunication::TYPES as $type) {
            foreach (['ar', 'en'] as $language) {
                $this->post($this->url(), $this->manual(compact('type', 'language') + ['created_by' => 999, 'event_id' => 999]))->assertRedirect()->assertSessionHasNoErrors();
            }
        }
        $this->assertDatabaseCount('event_communications', 6);
        $communication = $this->event->communications()->where('type', 'internal_email')->where('language', 'en')->firstOrFail();
        $this->assertSame($this->manager->id, $communication->created_by);
        $this->post($this->url(), $this->manual(['revision_number' => 1, 'content' => 'Revised', 'status' => 'ready']))->assertSessionHasNoErrors();
        $this->assertSame('Please join us.', $communication->revisions()->first()->content);
        $this->assertSame(2, $communication->fresh()->revision_number);
        $this->delete($this->url('/'.$communication->id), ['confirm' => 1, 'revision_number' => 2])->assertRedirect();
        $this->assertNotNull($communication->fresh()->archived_at);
        $this->post($this->url(), $this->manual(['revision_number' => 3]))->assertSessionHasNoErrors();
        $this->assertNull($communication->fresh()->archived_at);
        $this->assertCount(4, $communication->revisions);
    }

    public function test_validation_stale_edits_and_xss_are_handled(): void
    {
        $this->post($this->url(), $this->manual(['type' => 'unknown', 'language' => 'fr', 'status' => 'published']))->assertSessionHasErrors(['type', 'language']);
        $this->post($this->url(), $this->manual(['status' => 'ready', 'content' => '']))->assertSessionHasErrors('content');
        $this->post($this->url(), $this->manual(['title' => str_repeat('x', 181)]))->assertSessionHasErrors('title');
        $this->post($this->url(), $this->manual(['content' => '<script>alert(1)</script>']))->assertSessionHasNoErrors();
        $this->post($this->url(), $this->manual())->assertSessionHasErrors('revision_number');
        $this->get($this->url('?type=internal_email&language=en'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_event_viewers_cannot_mutate_or_generate_and_private_events_are_isolated(): void
    {
        $this->post($this->url(), $this->manual());
        $communication = EventCommunication::firstOrFail();
        $user = User::factory()->create();
        $user->assignRole(Access::STANDARD_USER);
        $this->signIn($user);
        $this->get($this->url())->assertForbidden();
        $this->event->update(['visibility' => 'all_users', 'status' => 'confirmed']);
        $this->get($this->url('?language=en'))->assertOk()->assertSee('Please join us.')->assertDontSee('data-communication-editor', false);
        $this->post($this->url(), $this->manual())->assertForbidden();
        $this->post($this->url('/generate'), $this->ai())->assertForbidden();
        $this->delete($this->url('/'.$communication->id), ['confirm' => 1, 'revision_number' => 1])->assertForbidden();
        $this->get('/admin/events/'.$this->event->slug.'/communications')->assertForbidden();
    }

    public function test_generation_is_idempotent_encrypted_bounded_and_does_not_overwrite_until_applied(): void
    {
        $payload = $this->ai();
        $generation = app(GenerateEventCommunication::class)->handle($this->manager, $this->event, $payload);
        $again = app(GenerateEventCommunication::class)->handle($this->manager, $this->event, $payload);
        $this->assertSame($generation->id, $again->id);
        $this->assertSame(1, EventCommunicationGeneration::count());
        $this->assertStringNotContainsString('Private conference', DB::table('event_communication_generations')->value('input_snapshot'));
        $this->runJob($generation);
        $this->runJob($generation);
        $this->assertCount(1, FakeAiProvider::$calls);
        $this->assertSame(20, $generation->fresh()->total_tokens);
        $snapshot = json_decode(FakeAiProvider::$calls[0]['messages'][1]['content'], true);
        $this->assertSame($this->event->title, $snapshot['event']['title']);
        $this->assertArrayNotHasKey('files', $snapshot);
        $this->assertSame(0, $generation->communication->revision_number);
        $this->post($this->url('/generations/'.$generation->id.'/apply'))->assertRedirect();
        $this->assertSame('Mocked assistant response', $generation->communication->fresh()->content);
        $this->assertSame('draft', $generation->communication->fresh()->status);
        $this->post($this->url('/generations/'.$generation->id.'/apply'))->assertStatus(409);
    }

    public function test_translate_uses_other_language_of_same_event_and_improve_requires_source(): void
    {
        $this->post($this->url('/generate'), $this->ai(['operation' => 'improve']))->assertSessionHasErrors('operation');
        $this->post($this->url('/generate'), $this->ai(['operation' => 'translate', 'language' => 'ar']))->assertSessionHasErrors('operation');
        $this->post($this->url(), $this->manual(['content' => 'English event source']));
        $generation = $this->generate(['operation' => 'translate', 'language' => 'ar']);
        $this->runJob($generation);
        $snapshot = json_decode(FakeAiProvider::$calls[0]['messages'][1]['content'], true);
        $this->assertSame('ar', $snapshot['language']);
        $this->assertSame('English event source', $snapshot['source']['content']);
        $this->assertSame(1, $snapshot['source']['revision_number']);
    }

    public function test_brand_context_is_opt_in_and_exact_version_is_preserved(): void
    {
        $this->post($this->url('/generate'), $this->ai(['use_brand_guidelines' => 1]))->assertSessionHasErrors('use_brand_guidelines');
        $guide = BrandGuideline::create(['title' => 'Voice']);
        $version = $guide->versions()->create(['version' => '1', 'storage_path' => 'private/one.txt', 'original_name' => 'one.txt', 'extension' => 'txt', 'mime_type' => 'text/plain', 'file_size' => 4, 'extracted_text' => 'Exact selected voice', 'scan_status' => 'clean', 'extraction_status' => 'ready', 'is_active' => true, 'activated_at' => now()]);
        $plain = $this->generate();
        $selected = $this->generate(['use_brand_guidelines' => 1]);
        $version->update(['is_active' => false]);
        $this->runJob($plain);
        $this->runJob($selected);
        $this->assertCount(2, FakeAiProvider::$calls[0]['messages']);
        $this->assertCount(3, FakeAiProvider::$calls[1]['messages']);
        $this->assertStringContainsString('Exact selected voice', FakeAiProvider::$calls[1]['messages'][1]['content']);
        $this->assertSame($version->id, $selected->brand_guideline_version_id);
    }

    public function test_missing_selected_context_fails_closed(): void
    {
        $guide = BrandGuideline::create(['title' => 'Voice']);
        $version = $guide->versions()->create(['version' => '1', 'storage_path' => 'private/one.txt', 'original_name' => 'one.txt', 'extension' => 'txt', 'mime_type' => 'text/plain', 'file_size' => 4, 'extracted_text' => 'Voice', 'scan_status' => 'clean', 'extraction_status' => 'ready', 'is_active' => true]);
        $generation = $this->generate(['use_brand_guidelines' => 1]);
        $version->update(['scan_status' => 'rejected']);
        $this->runJob($generation);
        $this->assertSame('failed', $generation->fresh()->status);
        $this->assertCount(0, FakeAiProvider::$calls);
    }

    public function test_provider_failure_model_revocation_and_disabled_user_are_safe(): void
    {
        $this->post($this->url('/generate'), $this->ai(['model' => 'forbidden']))->assertSessionHasErrors('model');
        $generation = $this->generate();
        FakeAiProvider::$failure = 'timeout';
        $this->runJob($generation);
        $this->assertSame('timeout', $generation->fresh()->failure_code);
        FakeAiProvider::reset();
        $generation = $this->generate();
        config(['ai.models' => []]);
        $this->runJob($generation);
        $this->assertSame('model_unavailable', $generation->fresh()->failure_code);
        $this->assertCount(0, FakeAiProvider::$calls);
        config(['ai.models' => ['gpt-test']]);
        $generation = $this->generate();
        $this->manager->forceFill(['status' => 'disabled'])->save();
        $this->runJob($generation);
        $this->assertSame('cancelled', $generation->fresh()->status);
        $this->assertCount(0, FakeAiProvider::$calls);
    }

    public function test_late_results_cannot_overwrite_newer_edits_or_cross_event_and_user_boundaries(): void
    {
        $generation = $this->generate();
        $this->runJob($generation);
        $other = Event::factory()->create();
        $this->post('/app/events/'.$other->slug.'/communications/generations/'.$generation->id.'/apply')->assertNotFound();
        $this->post($this->url(), $this->manual(['content' => 'Newer manual edit']))->assertSessionHasNoErrors();
        $this->post($this->url('/generations/'.$generation->id.'/apply'))->assertSessionHasErrors('revision_number');
        $this->assertSame('Newer manual edit', $generation->communication->fresh()->content);
        $otherManager = User::factory()->create();
        $otherManager->assignRole(Access::SUPER_ADMINISTRATOR);
        $this->signIn($otherManager);
        $this->get($this->url('/generations/'.$generation->id))->assertNotFound();
        $this->post($this->url('/generations/'.$generation->id.'/apply'))->assertNotFound();
    }

    public function test_archive_cancels_pending_work_and_hides_content_from_viewers(): void
    {
        $this->post($this->url(), $this->manual());
        $generation = $this->generate(['revision_number' => 1]);
        $this->delete($this->url('/'.$generation->event_communication_id), ['confirm' => 1, 'revision_number' => 1])->assertRedirect();
        $this->runJob($generation);
        $this->assertCount(0, FakeAiProvider::$calls);
        $this->assertSame('cancelled', $generation->fresh()->status);
        $this->event->update(['visibility' => 'all_users', 'status' => 'confirmed']);
        $viewer = User::factory()->create();
        $this->signIn($viewer);
        $this->get($this->url('?language=en'))->assertOk()->assertDontSee('Please join us.');
    }

    public function test_pending_requests_are_bounded_and_cross_event_foreign_keys_are_enforced(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->generate();
        }
        $this->post($this->url('/generate'), $this->ai())->assertSessionHasErrors('operation');
        $generation = EventCommunicationGeneration::firstOrFail();
        $other = Event::factory()->create();
        $this->expectException(QueryException::class);
        $generation->update(['event_id' => $other->id]);
    }

    public function test_job_discards_response_if_access_is_revoked_during_provider_call(): void
    {
        $generation = $this->generate();
        $provider = $this->mock(AiProvider::class);
        $provider->shouldReceive('generate')->once()->andReturnUsing(function () {
            $this->manager->forceFill(['status' => 'disabled'])->save();

            return new AiGenerationResult('Do not retain');
        });
        $this->runJob($generation);
        $this->assertSame('cancelled', $generation->fresh()->status);
        $this->assertNull($generation->fresh()->result);
    }
}
