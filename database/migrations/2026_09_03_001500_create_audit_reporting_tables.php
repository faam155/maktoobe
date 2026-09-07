<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 32);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('metadata');
            $table->string('source_key', 100)->nullable()->unique();
            $table->timestamp('created_at');
            $table->index(['created_at', 'id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['resource_type', 'resource_id', 'created_at'], 'audit_resource_date');
        });
        Schema::create('audited_configurations', function (Blueprint $table) {
            $table->string('scope', 20)->primary();
            $table->json('fingerprints');
            $table->timestamps();
        });
        foreach (['users', 'prompts', 'prompt_favorites', 'prompt_uses', 'event_files', 'event_reports', 'event_communications', 'event_communication_generations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->index('created_at', $name.'_analytics_created'));
        }
        DB::table('permissions')->insertOrIgnore(['name' => 'view-audit-logs', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('name', 'view-audit-logs')->where('guard_name', 'web')->value('id');
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', ['Super Administrator', 'Administrator'])->pluck('id') as $role) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['users', 'prompts', 'prompt_favorites', 'prompt_uses', 'event_files', 'event_reports', 'event_communications', 'event_communication_generations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropIndex($name.'_analytics_created'));
        }
        Schema::dropIfExists('audited_configurations');
        Schema::dropIfExists('audit_logs');
        // The additive permission may have been explicitly delegated; do not revoke it on schema rollback.
    }
};
