<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_platforms', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 40)->unique();
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('social_media_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('platform_id')->constrained('social_platforms')->restrictOnDelete();
            $t->string('name', 160);
            $t->string('identifier', 160);
            $t->string('url', 500)->nullable();
            $t->string('timezone', 80)->default('Asia/Muscat');
            $t->boolean('active')->default(true);
            $t->json('settings')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamp('last_sync_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['platform_id', 'identifier']);
        });
        Schema::create('social_content_types', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 40)->unique();
            $t->string('name', 80);
            $t->string('name_ar', 100);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        foreach (['social_campaigns', 'social_tags'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
                $t->string('name', 160);
                $t->text('description')->nullable();
                $t->boolean('active')->default(true);
                if ($table === 'social_campaigns') {
                    $t->string('kind', 20)->default('campaign');
                    $t->date('starts_on')->nullable();
                    $t->date('ends_on')->nullable();
                }
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['social_account_id', 'active']);
            });
        }
        Schema::create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->string('platform_post_id', 180);
            $t->string('title', 200);
            $t->text('content')->nullable();
            $t->string('url', 500)->nullable();
            $t->string('thumbnail_url', 500)->nullable();
            $t->timestamp('published_at')->index();
            $t->foreignId('content_type_id')->constrained('social_content_types')->restrictOnDelete();
            $t->string('language', 20);
            $t->foreignId('campaign_id')->nullable()->constrained('social_campaigns')->restrictOnDelete();
            $t->foreignId('event_id')->nullable()->constrained('events')->restrictOnDelete();
            $t->string('status', 20)->default('published');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['social_account_id', 'platform_post_id']);
            $t->index(['social_account_id', 'published_at']);
            $t->index(['campaign_id', 'published_at']);
        });
        Schema::create('social_post_tags', function (Blueprint $t) {
            $t->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('social_tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['social_post_id', 'social_tag_id']);
        });
        foreach (['account', 'post'] as $subject) {
            Schema::create('social_'.$subject.'_metrics', function (Blueprint $t) use ($subject) {
                $t->id();
                $t->foreignId('social_'.$subject.'_id')->constrained($subject === 'account' ? 'social_media_accounts' : 'social_posts')->restrictOnDelete();
                $t->date('starts_on');
                $t->date('ends_on');
                foreach (['impressions', 'reactions', 'comments', 'shares', 'clicks'] as $metric) {
                    $t->unsignedBigInteger($metric)->nullable();
                }
                if ($subject === 'account') {
                    $t->unsignedBigInteger('followers')->nullable();
                    $t->unsignedBigInteger('new_followers')->nullable();
                }
                $t->string('source', 20)->default('manual');
                $t->unsignedInteger('revision')->default(1);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->unique(['social_'.$subject.'_id', 'starts_on', 'ends_on'], 'social_'.$subject.'_metric_period');
            });
        }
        Schema::create('social_metric_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_metric_id')->nullable()->constrained('social_account_metrics')->restrictOnDelete();
            $t->foreignId('post_metric_id')->nullable()->constrained('social_post_metrics')->restrictOnDelete();
            $t->json('values');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at');
        });
        Schema::create('social_audience_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->date('metric_date');
            $t->string('dimension', 30);
            $t->string('value', 160);
            $t->unsignedBigInteger('audience_count')->nullable();
            $t->decimal('percentage', 7, 4)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['social_account_id', 'metric_date', 'dimension', 'value'], 'social_audience_identity');
        });
        Schema::create('social_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month')->default(0);
            $t->string('metric', 40);
            $t->decimal('value', 18, 4);
            $t->boolean('active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['social_account_id', 'year', 'month', 'metric']);
        });
        Schema::create('social_sync_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20);
            $t->string('status', 20);
            $t->string('error_code', 80)->nullable();
            $t->unsignedInteger('processed')->default(0);
            $t->unsignedInteger('created')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->timestamp('started_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['social_account_id', 'started_at']);
        });
        Schema::create('social_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status', 20);
            $t->longText('preview');
            $t->json('errors');
            $t->string('sha256', 64);
            $t->unsignedInteger('rows');
            $t->timestamps();
        });
        Schema::create('social_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('name', 160);
            $t->json('filters');
            $t->json('settings');
            $t->longText('summary')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['user_id', 'social_account_id']);
        });
        Schema::create('social_ai_insights', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_account_id')->constrained('social_media_accounts')->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->uuid('operation_id')->unique();
            $t->string('status', 20);
            $t->string('model', 100);
            $t->json('filters');
            $t->json('settings');
            $t->longText('snapshot');
            $t->longText('content')->nullable();
            $t->longText('recommendations')->nullable();
            $t->string('error_code', 80)->nullable();
            $t->unsignedInteger('total_tokens')->nullable();
            $t->unsignedInteger('reserved_tokens');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        foreach (['account', 'post'] as $subject) {
            DB::statement('ALTER TABLE social_'.$subject.'_metrics ADD CONSTRAINT social_'.$subject.'_ordered_interval CHECK (ends_on >= starts_on)');
        }
        DB::statement('ALTER TABLE social_targets ADD CONSTRAINT social_target_valid CHECK (month <= 12 AND value > 0)');
        DB::statement('ALTER TABLE social_audience_metrics ADD CONSTRAINT social_audience_percentage CHECK (percentage IS NULL OR (percentage >= 0 AND percentage <= 100))');
        DB::statement('ALTER TABLE social_metric_revisions ADD CONSTRAINT social_revision_subject CHECK ((account_metric_id IS NULL) <> (post_metric_id IS NULL))');
    }

    public function down(): void
    {
        foreach (['social_ai_insights', 'social_reports', 'social_imports', 'social_sync_logs', 'social_targets', 'social_audience_metrics', 'social_metric_revisions', 'social_post_metrics', 'social_account_metrics', 'social_post_tags', 'social_posts', 'social_tags', 'social_campaigns', 'social_content_types', 'social_media_accounts', 'social_platforms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
