<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Contracts\GuidelineFileScanner;
use App\Contracts\PrivateFileScanner;
use App\Contracts\SocialMediaProvider;
use App\Models\AiConversation;
use App\Models\Event;
use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\User;
use App\Policies\AiConversationPolicy;
use App\Policies\EventPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PromptCategoryPolicy;
use App\Policies\PromptPolicy;
use App\Policies\RolePolicy;
use App\Services\Ai\LocalAiProvider;
use App\Services\Ai\OpenAiResponsesProvider;
use App\Services\Brand\LocalGuidelineFileScanner;
use App\Services\Social\LinkedInProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(SocialMediaProvider::class, LinkedInProvider::class);
        $this->app->bind(AiProvider::class, fn ($app) => config('ai.provider') === 'local' ? $app->make(LocalAiProvider::class) : $app->make(OpenAiResponsesProvider::class));
        $this->app->bind(GuidelineFileScanner::class, LocalGuidelineFileScanner::class);
        $this->app->bind(PrivateFileScanner::class, fn ($app) => $app->make(GuidelineFileScanner::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['social-read' => 120, 'social-write' => 20, 'social-export' => 5, 'social-ai' => 3] as $name => $maximum) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($maximum)->by($name.':'.($request->user()?->id ?? $request->ip())));
        }
        Relation::enforceMorphMap(['user' => User::class]);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(PromptCategory::class, PromptCategoryPolicy::class);
        Gate::policy(Prompt::class, PromptPolicy::class);
        Gate::policy(AiConversation::class, AiConversationPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
    }
}
