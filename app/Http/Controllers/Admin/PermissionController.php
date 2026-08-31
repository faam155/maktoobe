<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class PermissionController
{
    public function __invoke(): mixed
    {
        Gate::authorize('viewAny', Permission::class);

        return view('admin.permissions.index', [
            'permissions' => Permission::withCount('roles')->orderBy('name')->paginate(20),
        ]);
    }
}
