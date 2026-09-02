<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->unsignedSmallInteger('display_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('event_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->unique(['event_category_id', 'locale']);
            $table->index(['locale', 'name']);
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 190)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('event_categories')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('location', 255)->nullable();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('visibility', 24)->default('private');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['starts_at', 'ends_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['visibility', 'starts_at']);
            $table->index(['organizer_id', 'starts_at']);
        });
        Schema::create('event_user_access', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['event_id', 'user_id']);
            $table->index(['user_id', 'event_id']);
        });
        Schema::create('event_role_access', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['event_id', 'role_id']);
            $table->index(['role_id', 'event_id']);
        });
        Schema::create('event_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_activities');
        Schema::dropIfExists('event_role_access');
        Schema::dropIfExists('event_user_access');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_category_translations');
        Schema::dropIfExists('event_categories');
    }
};
