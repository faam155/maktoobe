<?php

namespace Tests\Feature;

use App\Actions\Notifications\RecordWorkspaceNotice;
use App\Models\User;
use App\Models\WorkspaceNotice;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationQueueTest extends TestCase
{
    use DatabaseMigrations;

    public function test_delivery_waits_for_commit_and_rollback_does_not_enqueue(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        DB::beginTransaction();
        app(RecordWorkspaceNotice::class)->handle('system', 'rolled-back');
        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();
        $this->assertSame(0, WorkspaceNotice::count());
        $this->assertSame(0, DB::table('jobs')->count());

        DB::beginTransaction();
        app(RecordWorkspaceNotice::class)->handle('system', 'committed');
        $this->assertSame(0, DB::table('jobs')->count());
        DB::commit();
        $this->assertSame(1, DB::table('jobs')->where('queue', 'notifications')->count());
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'notifications', '--once' => true, '--tries' => 1]);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertNotNull(WorkspaceNotice::firstOrFail()->completed_at);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
