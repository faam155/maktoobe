<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('ends_at');
            $table->index(['category_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        // MySQL may discard the original FK index when the composite covers it.
        if (! Schema::hasIndex('events', 'events_category_id_index')) {
            Schema::table('events', fn (Blueprint $table) => $table->index('category_id'));
        }
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['ends_at']);
            $table->dropIndex(['category_id', 'starts_at']);
        });
    }
};
