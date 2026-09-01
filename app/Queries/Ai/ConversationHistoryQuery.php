<?php

namespace App\Queries\Ai;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ConversationHistoryQuery
{
    public function get(User $user, array $input): LengthAwarePaginator
    {
        Gate::forUser($user)->authorize('viewAny', AiConversation::class);
        $data = Validator::make($input, [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,archived,all'],
            'sort' => ['nullable', 'in:recent,oldest,title'],
        ])->validate();

        $query = $user->aiConversations()->withCount('messages');
        $search = trim((string) ($data['search'] ?? ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%');
        }

        match ($data['status'] ?? 'active') {
            'archived' => $query->whereNotNull('archived_at'),
            'all' => null,
            default => $query->whereNull('archived_at'),
        };

        match ($data['sort'] ?? 'recent') {
            'oldest' => $query->orderByRaw('COALESCE(last_message_at, created_at) ASC')->orderBy('id'),
            'title' => $query->orderBy('title')->orderByDesc('id'),
            default => $query->orderByRaw('COALESCE(last_message_at, created_at) DESC')->orderByDesc('id'),
        };

        return $query->paginate(15)->withQueryString();
    }
}
