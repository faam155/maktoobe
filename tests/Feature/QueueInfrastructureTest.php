<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\Fixtures\RecordQueueProbe;
use Tests\TestCase;

class QueueInfrastructureTest extends TestCase
{
    // Real commits are required here; an outer RefreshDatabase transaction would mask them.
    // The shared TestCase verifies the disposable database before this trait can reset it.
    use DatabaseMigrations;

    public function test_jobs_wait_for_the_outer_commit_then_run_through_the_database_worker(): void
    {
        DB::beginTransaction();
        DB::beginTransaction();

        Bus::dispatch((new RecordQueueProbe('committed'))->onConnection('database')->onQueue('foundation-tests'));
        $this->assertDatabaseCount('jobs', 0);

        DB::commit();
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();

        $this->assertDatabaseCount('jobs', 1);
        $this->assertNull(Cache::store('database')->get('committed'));

        $this->runWorker();

        $this->assertSame('processed', Cache::store('database')->get('committed'));
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_rolled_back_work_is_never_dispatched(): void
    {
        DB::beginTransaction();
        Bus::dispatch((new RecordQueueProbe('rolled-back'))->onConnection('database')->onQueue('foundation-tests'));
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();

        // A later commit must not resurrect the cancelled callback.
        DB::transaction(fn () => DB::select('SELECT 1'));
        $this->runWorker();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertNull(Cache::store('database')->get('rolled-back'));
    }

    public function test_exhausted_jobs_are_recorded_for_inspection_without_success_side_effects(): void
    {
        // Expected failure is captured, not written as noise to the application's log.
        Exceptions::fake([RuntimeException::class]);
        Bus::dispatch((new RecordQueueProbe('failed', fail: true))->onConnection('database')->onQueue('foundation-tests'));

        $this->runWorker();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseHas('failed_jobs', ['connection' => 'database', 'queue' => 'foundation-tests']);
        $this->assertStringContainsString('Expected queue probe failure.', DB::table('failed_jobs')->value('exception'));
        $this->assertNull(Cache::store('database')->get('failed'));
        Exceptions::assertReported(RuntimeException::class);
    }

    private function runWorker(): void
    {
        $this->artisan('queue:work', [
            'connection' => 'database',
            '--queue' => 'foundation-tests',
            '--once' => true,
            '--sleep' => 0,
            '--tries' => 1,
            '--timeout' => 30,
        ])->assertSuccessful();
    }
}
