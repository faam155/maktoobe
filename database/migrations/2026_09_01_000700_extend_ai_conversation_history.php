<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->timestamp('last_message_at')->nullable()->after('model');
            $table->timestamp('archived_at')->nullable()->after('last_message_at');
            $table->index(['user_id', 'archived_at', 'last_message_at'], 'ai_conversations_user_archive_activity_index');
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->string('model', 100)->nullable()->after('role');
        });

        DB::statement('UPDATE ai_conversations c SET last_message_at = (SELECT MAX(m.created_at) FROM ai_messages m WHERE m.conversation_id = c.id)');
        DB::statement('UPDATE ai_messages m JOIN ai_conversations c ON c.id = m.conversation_id SET m.model = c.model WHERE m.model IS NULL');
    }

    public function down(): void
    {
        Schema::table('ai_messages', fn (Blueprint $table) => $table->dropColumn('model'));
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropIndex('ai_conversations_user_archive_activity_index');
            $table->dropColumn(['last_message_at', 'archived_at']);
        });
    }
};
