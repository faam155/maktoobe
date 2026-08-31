<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class DashboardController
{
    public function __invoke(Request $request): mixed
    {
        return view('admin.dashboard', [
            'userCount' => $request->user()->can('manage-users') ? User::count() : null,
            'roleCount' => $request->user()->can('manage-roles') ? Role::count() : null,
        ]);
    }
}
