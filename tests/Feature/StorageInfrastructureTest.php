<?php

namespace Tests\Feature;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\PathTraversalDetected;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use Tests\TestCase;

class StorageInfrastructureTest extends TestCase
{
    public function test_a_private_file_round_trips_but_is_not_accessible_over_http(): void
    {
        $path = 'foundation-probe-'.Str::uuid().'.txt';
        $disk = Storage::disk('local');

        try {
            $this->assertTrue($disk->put($path, 'نص خاص / private text'));
            $this->assertSame('نص خاص / private text', $disk->get($path));
            $this->assertSame(realpath(storage_path('app/private/'.$path)), realpath($disk->path($path)));
            $this->assertFileDoesNotExist(public_path('storage/'.$path));
            $this->get('/storage/'.$path)->assertNotFound()->assertDontSee('private text');
            $this->get('/storage/local/'.$path)->assertNotFound()->assertDontSee('private text');
        } finally {
            $disk->delete($path);
        }
    }

    public function test_private_write_failures_are_thrown_instead_of_silently_returned(): void
    {
        $driver = Mockery::mock(FilesystemOperator::class);
        $driver->shouldReceive('write')->once()->andThrow(UnableToWriteFile::atLocation('probe.txt'));
        $disk = new FilesystemAdapter($driver, Storage::disk('local')->getAdapter(), config('filesystems.disks.local'));

        $this->expectException(UnableToWriteFile::class);
        $disk->put('probe.txt', 'test');
    }

    public function test_private_storage_rejects_paths_that_escape_its_root(): void
    {
        $this->expectException(PathTraversalDetected::class);
        Storage::disk('local')->put('../foundation-escape.txt', 'must not be written');
    }
}
