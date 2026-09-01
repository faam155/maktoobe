<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_guidelines', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['title', 'created_at']);
        });

        Schema::create('brand_guideline_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_guideline_id')->constrained()->cascadeOnDelete();
            $table->string('version', 60);
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('extension', 12);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->longText('extracted_text')->nullable();
            $table->enum('extraction_status', ['ready', 'not_supported'])->default('not_supported');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['brand_guideline_id', 'version']);
            $table->index(['is_active', 'activated_at']);
            $table->index(['brand_guideline_id', 'created_at']);
        });

        Schema::table('ai_requests', function (Blueprint $table) {
            $table->foreignId('brand_guideline_version_id')->nullable()->after('prompt_id')
                ->constrained('brand_guideline_versions')->restrictOnDelete();
            $table->index(['brand_guideline_version_id', 'requested_at'], 'ai_requests_brand_version_requested_index');
        });
    }

    public function down(): void
    {
        Schema::table('ai_requests', function (Blueprint $table) {
            $table->dropForeign(['brand_guideline_version_id']);
            $table->dropIndex('ai_requests_brand_version_requested_index');
            $table->dropColumn('brand_guideline_version_id');
        });
        Schema::dropIfExists('brand_guideline_versions');
        Schema::dropIfExists('brand_guidelines');
    }
};
