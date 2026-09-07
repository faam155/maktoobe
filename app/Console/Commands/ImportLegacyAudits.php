<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAudit;
use App\Models\EventActivity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyAudits extends Command
{
    protected $signature = 'audit:import-legacy';

    protected $description = 'Idempotently import sanitized existing account and event audit facts';

    public function handle(): int
    {
        DB::table('account_audits')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                app(RecordAudit::class)->account($row);
            }
        });
        EventActivity::orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                app(RecordAudit::class)->event($row);
            }
        });
        $this->info('Existing audit facts imported without content or credentials.');

        return self::SUCCESS;
    }
}
