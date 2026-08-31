<?php

namespace App\Queries\Administration;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserDirectory
{
    public function get(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $term = '%'.addcslashes(trim($search), '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('username', 'like', $term));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, int $role) => $query->whereHas('roles', fn ($roles) => $roles->where('roles.id', $role)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }
}
