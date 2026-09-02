<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_files', fn (Blueprint $table) => $table->unique(['event_id', 'id'], 'event_files_scoped_key'));
        Schema::create('event_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['PRE_EVENT', 'POST_EVENT']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['event_id', 'type']);
            $table->unique(['event_id', 'id'], 'event_reports_scoped_key');
        });
        Schema::create('event_report_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_report_id');
            $table->unsignedBigInteger('event_file_id')->unique();
            $table->unsignedInteger('version_number');
            $table->string('title', 180);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['event_report_id', 'version_number'], 'event_report_version_number_unique');
            $table->index(['event_report_id', 'deleted_at', 'version_number'], 'event_report_history_index');
            $table->foreign(['event_id', 'event_report_id'], 'event_report_version_parent_fk')->references(['event_id', 'id'])->on('event_reports')->restrictOnDelete();
            $table->foreign(['event_id', 'event_file_id'], 'event_report_version_file_fk')->references(['event_id', 'id'])->on('event_files')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_report_versions');
        Schema::dropIfExists('event_reports');
        Schema::table('event_files', fn (Blueprint $table) => $table->dropUnique('event_files_scoped_key'));
    }
};
