<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_notices', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->string('operation_key', 180)->unique();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('report_version_id')->nullable()->constrained('event_report_versions')->restrictOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('broadcast')->default(true);
            $table->timestamp('occurrence_at')->nullable();
            $table->json('system_content')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('audience_ceiling');
            $table->unsignedBigInteger('last_user_id')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['completed_at', 'id']);
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->foreignId('notice_id')->nullable()->constrained('workspace_notices')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'notice_id']);
            $table->index(['user_id', 'dismissed_at', 'read_at', 'created_at'], 'notification_inbox');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('workspace_notices');
    }
};
