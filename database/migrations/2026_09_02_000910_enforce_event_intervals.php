<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->change();
        });
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_valid_interval CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE events DROP CHECK events_valid_interval');
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('starts_at')->change();
            $table->timestamp('ends_at')->change();
        });
    }
};
