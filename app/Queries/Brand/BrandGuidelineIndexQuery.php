<?php

namespace App\Queries\Brand;

use App\Models\BrandGuideline;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class BrandGuidelineIndexQuery
{
    public function handle(User $actor): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', BrandGuideline::class);

        return BrandGuideline::with(['versions' => fn ($query) => $query->latest('created_at')])
            ->withCount('versions')->latest()->paginate(15);
    }
}
