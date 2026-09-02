<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('category', 24);
            $table->string('original_name', 180);
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path', 190)->unique();
            $table->string('mime_type', 120);
            $table->string('extension', 8);
            $table->unsignedBigInteger('file_size');
            $table->string('caption', 500)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('scan_status', 16)->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'category', 'deleted_at', 'display_order', 'id'], 'event_files_gallery_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_files');
    }
};
