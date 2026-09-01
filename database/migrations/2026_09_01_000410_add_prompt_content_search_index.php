<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', fn (Blueprint $table) => $table->fullText('content', 'prompts_content_fulltext'));
    }

    public function down(): void
    {
        Schema::table('prompts', fn (Blueprint $table) => $table->dropFullText('prompts_content_fulltext'));
    }
};
