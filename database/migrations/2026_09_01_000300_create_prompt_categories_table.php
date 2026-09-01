<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'display_order']);
        });

        Schema::create('prompt_category_translations', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('prompt_categories')->cascadeOnDelete();
            $table->enum('locale', ['en', 'ar']);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->primary(['category_id', 'locale']);
            $table->index(['locale', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_category_translations');
        Schema::dropIfExists('prompt_categories');
    }
};
