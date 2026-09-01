<?php

namespace App\Http\Controllers\Portal;

use App\Queries\Dashboard\PortalDashboardQuery;
use Illuminate\Http\Request;

class DashboardController
{
    public function __invoke(Request $request, PortalDashboardQuery $dashboard): mixed
    {
        return view('portal.dashboard', $dashboard->get($request->user()));
    }
}
