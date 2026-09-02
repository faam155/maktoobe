<?php

namespace Tests\Feature;

use App\Actions\Events\GenerateEventCommunication;
use App\Contracts\AiProvider;
use App\Models\Event;
use App\Models\User;
use App\Support\Authorization\Access;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fixtures\FakeAiProvider;
use Tests\TestCase;

class EventCommunicationQueueTest extends TestCase
{
    use DatabaseMigrations;

    public function test_generation_waits_for_commit_and_worker_persists_only_a_suggestion(): void
    {
        $this->seed(AccessControlSeeder::class);
        config(['queue.default' => 'database', 'ai.models' => ['gpt-test'], 'ai.default_model' => 'gpt-test', 'ai.role_models' => []]);
        $this->app->bind(AiProvider::class, FakeAiProvider::class);
        FakeAiProvider::reset();
        $user = User::factory()->create();
        $user->assignRole(Access::SUPER_ADMINISTRATOR);
        $event = Event::factory()->create();
        DB::beginTransaction();
        $generation = app(GenerateEventCommunication::class)->handle($user, $event, ['type' => 'general_copy', 'language' => 'en', 'operation' => 'generate', 'model' => 'gpt-test', 'revision_number' => 0, 'client_operation_id' => (string) Str::uuid()]);
        $this->assertSame(0, DB::table('jobs')->count());
        DB::commit();
        $this->assertSame(1, DB::table('jobs')->where('queue', 'ai')->count());
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'ai', '--once' => true, '--tries' => 1]);
        $this->assertSame('completed', $generation->fresh()->status);
        $this->assertSame(0, $generation->communication->revision_number);
        $this->assertCount(1, FakeAiProvider::$calls);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
