<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_guideline_versions', function (Blueprint $table) {
            $table->string('scan_status', 20)->default('pending')->after('extraction_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('brand_guideline_versions', function (Blueprint $table) {
            $table->dropIndex(['scan_status']);
            $table->dropColumn('scan_status');
        });
    }
};
