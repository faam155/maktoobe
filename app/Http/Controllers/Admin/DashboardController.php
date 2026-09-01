<?php

namespace App\Http\Controllers\Admin;

use App\Queries\Dashboard\AdminDashboardQuery;
use Illuminate\Http\Request;

class DashboardController
{
    public function __invoke(Request $request, AdminDashboardQuery $dashboard): mixed
    {
        return view('admin.dashboard', $dashboard->get($request->user()));
    }
}
