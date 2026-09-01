<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Contracts\GuidelineFileScanner;
use App\Models\AiConversation;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\User;
use App\Policies\AiConversationPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PromptCategoryPolicy;
use App\Policies\PromptPolicy;
use App\Policies\RolePolicy;
use App\Services\Ai\LocalAiProvider;
use App\Services\Ai\OpenAiResponsesProvider;
use App\Services\Brand\LocalGuidelineFileScanner;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProvider::class, fn ($app) => config('ai.provider') === 'local' ? $app->make(LocalAiProvider::class) : $app->make(OpenAiResponsesProvider::class));
        $this->app->bind(GuidelineFileScanner::class, LocalGuidelineFileScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap(['user' => User::class]);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(PromptCategory::class, PromptCategoryPolicy::class);
        Gate::policy(Prompt::class, PromptPolicy::class);
        Gate::policy(AiConversation::class, AiConversationPolicy::class);
    }
}
