<?php

namespace App\Queries\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Reporting\ReportPeriod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuditQuery
{
    public function types(User $actor): array
    {
        $permissions = ['social' => 'linkedin.settings.manage', 'user' => 'manage-users', 'role' => 'manage-roles', 'prompt' => 'manage-prompts', 'prompt_category' => 'manage-categories', 'event' => 'manage-events', 'brand_guideline' => 'manage-brand-guidelines', 'system' => 'manage-system-settings', 'ai_configuration' => 'manage-ai-settings'];

        return array_keys(array_filter($permissions, fn ($permission) => $permission === 'manage-roles' ? $actor->canAny(['manage-roles', 'manage-permissions']) : $actor->can($permission)));
    }

    public function get(User $actor, ReportPeriod $period, array $input): array
    {
        Gate::forUser($actor)->authorize('access-admin');
        Gate::forUser($actor)->authorize('view-audit-logs');
        abort_unless($actor->status->value === 'active' && $actor->hasVerifiedEmail() && ! $actor->trashed(), 403);
        $data = Validator::make($input, [
            'search' => ['nullable', 'string', 'max:80'], 'actor_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.]*$/D'],
            'resource_type' => ['nullable', Rule::in($this->types($actor))],
            'resource_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ])->validate();
        $base = $period->apply(AuditLog::whereIn('resource_type', $this->types($actor)));
        $actions = (clone $base)->select('action')->distinct()->orderBy('action')->pluck('action');
        $query = clone $base;
        foreach (['actor_id', 'action', 'resource_type', 'resource_id'] as $key) {
            if (filled($data[$key] ?? null)) {
                $query->where($key, $data[$key]);
            }
        }
        if (filled($data['search'] ?? null)) {
            $term = '%'.addcslashes($data['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('action', 'like', $term)->orWhere('resource_type', 'like', $term)->orWhereHas('actor', fn ($u) => $u->where('name', 'like', $term)));
        }
        $logs = $query->with('actor:id,name')->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString();

        return ['logs' => $logs, 'actions' => $actions, 'types' => $this->types($actor)];
    }
}
