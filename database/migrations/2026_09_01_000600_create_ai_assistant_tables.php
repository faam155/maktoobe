<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('model', 100);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->unsignedInteger('prompt_revision')->nullable();
            $table->longText('prompt_snapshot')->nullable();
            $table->foreignId('user_message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->uuid('client_operation_id');
            $table->string('model', 100);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->json('settings_snapshot');
            $table->string('provider_request_id', 120)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_operation_id']);
            $table->index(['conversation_id', 'requested_at']);
            $table->index(['status', 'requested_at']);
            $table->index(['user_id', 'requested_at']);
        });

        Schema::table('prompt_uses', function (Blueprint $table) {
            $table->foreignId('ai_request_id')->nullable()->after('prompt_id')->constrained('ai_requests')->nullOnDelete();
            $table->unique('ai_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('prompt_uses', function (Blueprint $table) {
            $table->dropForeign(['ai_request_id']);
            $table->dropUnique(['ai_request_id']);
            $table->dropColumn('ai_request_id');
        });
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
