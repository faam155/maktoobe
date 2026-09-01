<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('prompt_categories')->restrictOnDelete();
            $table->enum('source', ['library', 'personal'])->default('library');
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->longText('content');
            $table->string('content_locale', 10)->nullable();
            $table->enum('visibility', ['private', 'selected_roles', 'selected_users', 'all_users'])->default('private');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source', 'status', 'visibility', 'category_id'], 'prompts_discovery_index');
            $table->index(['status', 'published_at']);
            $table->index(['owner_id', 'updated_at']);
            $table->fullText(['title', 'description', 'content'], 'prompts_search_fulltext');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name', 80)->unique();
            $table->string('display_name', 80);
            $table->timestamps();
        });

        Schema::create('prompt_tag', function (Blueprint $table) {
            $table->foreignId('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['prompt_id', 'tag_id']);
            $table->index(['tag_id', 'prompt_id']);
        });

        Schema::create('prompt_user_access', function (Blueprint $table) {
            $table->foreignId('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['prompt_id', 'user_id']);
            $table->index(['user_id', 'prompt_id']);
        });

        Schema::create('prompt_role_access', function (Blueprint $table) {
            $table->foreignId('prompt_id')->constrained('prompts')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['prompt_id', 'role_id']);
            $table->index(['role_id', 'prompt_id']);
        });

        Schema::create('prompt_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->enum('kind', ['copy', 'ai'])->default('copy');
            $table->uuid('client_operation_id');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'client_operation_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['prompt_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_uses');
        Schema::dropIfExists('prompt_role_access');
        Schema::dropIfExists('prompt_user_access');
        Schema::dropIfExists('prompt_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('prompts');
    }
};
