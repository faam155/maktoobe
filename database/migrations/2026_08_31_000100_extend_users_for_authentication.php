<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique();
            $table->string('phone_e164', 16)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('locale', 2)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedInteger('security_version')->default(1);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->string('password')->nullable()->change();
        });
        DB::table('users')->orderBy('id')->eachById(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['username' => 'existing_'.$user->id]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('username', 32)->nullable(false)->change());
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('password')->exists()) {
            throw new RuntimeException('Cannot remove identity support while passwordless accounts exist.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disabled_by');
            $table->dropColumn(['username', 'phone_e164', 'phone_verified_at', 'status', 'locale', 'timezone', 'security_version', 'disabled_at', 'deleted_at']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
