<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['internal_email', 'linkedin_post', 'general_copy']);
            $table->enum('language', ['ar', 'en']);
            $table->string('title', 180)->default('');
            $table->longText('content')->nullable();
            $table->enum('status', ['draft', 'ready', 'approved', 'used'])->default('draft');
            $table->unsignedInteger('revision_number')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'type', 'language'], 'event_communication_slot');
            $table->unique(['event_id', 'id'], 'event_communication_scope');
        });
        Schema::create('event_communication_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_communication_id');
            $table->foreign(['event_id', 'event_communication_id'], 'communication_generation_parent')->references(['event_id', 'id'])->on('event_communications')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_operation_id');
            $table->unique(['user_id', 'client_operation_id'], 'communication_generation_operation');
            $table->unsignedInteger('base_revision');
            $table->string('operation', 20);
            $table->string('model', 100);
            $table->string('status', 20)->default('queued');
            $table->longText('input_snapshot');
            $table->longText('result')->nullable();
            $table->json('settings_snapshot');
            $table->unsignedBigInteger('brand_guideline_version_id')->nullable();
            $table->foreign('brand_guideline_version_id', 'communication_generation_brand')->references('id')->on('brand_guideline_versions')->restrictOnDelete();
            $table->string('failure_code', 40)->nullable();
            $table->string('provider_request_id')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'communication_generation_queue');
            $table->index(['event_communication_id', 'user_id', 'id'], 'communication_generation_history');
        });
        Schema::create('event_communication_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_communication_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 180);
            $table->longText('content')->nullable();
            $table->string('status', 20);
            $table->string('origin', 20);
            $table->foreignId('generation_id')->nullable()->unique()->constrained('event_communication_generations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['event_communication_id', 'version_number'], 'communication_revision_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_communication_revisions');
        Schema::dropIfExists('event_communication_generations');
        Schema::dropIfExists('event_communications');
    }
};
