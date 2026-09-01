<?php

namespace App\Providers;

use App\Models\Prompt;
use App\Models\PromptCategory;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\PromptCategoryPolicy;
use App\Policies\PromptPolicy;
use App\Policies\RolePolicy;
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
        //
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
    }
}
