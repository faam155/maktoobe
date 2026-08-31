<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoggingInfrastructureTest extends TestCase
{
    public function test_daily_logs_write_safe_context_and_filter_debug_messages(): void
    {
        $prefix = storage_path('framework/testing/log-probe-'.Str::uuid());
        $logger = Log::build(array_replace(config('logging.channels.daily'), ['path' => $prefix.'.log']));

        try {
            $logger->info('foundation.health', ['component' => 'queue', 'status' => 'ok']);
            $logger->debug('test-only-debug-message');
            $logger->getLogger()->close();

            $files = File::glob($prefix.'-*.log');
            $this->assertCount(1, $files);
            $content = File::get($files[0]);
            $this->assertStringContainsString('foundation.health', $content);
            $this->assertStringContainsString('"status":"ok"', $content);
            $this->assertStringNotContainsString('test-only-debug-message', $content);
        } finally {
            $logger->getLogger()->close();
            File::delete(File::glob($prefix.'-*.log'));
        }
    }
}
