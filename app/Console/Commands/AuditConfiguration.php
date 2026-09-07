<?php

namespace App\Console\Commands;

use App\Actions\Audit\RecordAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditConfiguration extends Command
{
    protected $signature = 'audit:configuration';

    protected $description = 'Detect approved non-secret server configuration changes';

    public function handle(): int
    {
        $scopes = [
            'ai_configuration' => array_intersect_key(config('ai'), array_flip(['provider', 'default_model', 'models', 'role_models', 'max_output_tokens', 'temperature', 'timeout', 'connect_timeout'])),
            'system' => ['timezone' => config('app.timezone'), 'locale' => config('app.locale'), 'session_lifetime' => config('session.lifetime'), 'session_secure' => config('session.secure')],
        ];
        foreach ($scopes as $scope => $values) {
            $fingerprints = array_map(fn ($value) => hash('sha256', json_encode($value, JSON_THROW_ON_ERROR)), $values);
            DB::transaction(function () use ($scope, $fingerprints) {
                DB::table('audited_configurations')->insertOrIgnore(['scope' => $scope, 'fingerprints' => json_encode($fingerprints), 'created_at' => now(), 'updated_at' => now()]);
                $row = DB::table('audited_configurations')->where('scope', $scope)->lockForUpdate()->first();
                $before = json_decode($row->fingerprints, true);
                $changed = array_keys(array_diff_assoc($fingerprints, $before));
                if ($changed) {
                    app(RecordAudit::class)->handle(null, $scope === 'system' ? 'system.configuration_changed' : 'ai.configuration_changed', $scope, null, ['changed_keys' => $changed]);
                    DB::table('audited_configurations')->where('scope', $scope)->update(['fingerprints' => json_encode($fingerprints), 'updated_at' => now()]);
                }
            });
        }
        $this->info('Non-secret configuration fingerprints checked; initial observations establish a baseline.');

        return self::SUCCESS;
    }
}
