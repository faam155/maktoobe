<?php

namespace App\Actions\Brand;

use App\Actions\Identity\RecordAccountAudit;
use App\Models\BrandGuideline;
use App\Models\BrandGuidelineVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetActiveBrandGuidelineVersion
{
    public function handle(User $actor, BrandGuidelineVersion $version, bool $active): void
    {
        Gate::forUser($actor)->authorize('update', $version);
        if ($active && $version->scan_status !== 'clean') {
            throw ValidationException::withMessages(['active' => __('brand.file_not_scanned')]);
        }
        DB::transaction(function () use ($actor, $version, $active) {
            BrandGuideline::query()->lockForUpdate()->get(['id']);
            if ($active) {
                BrandGuidelineVersion::where('is_active', true)->update(['is_active' => false, 'activated_at' => null]);
            }
            $version->update(['is_active' => $active, 'activated_at' => $active ? now() : null]);
            app(RecordAccountAudit::class)->handle($actor, $active ? 'brand_guideline.activated' : 'brand_guideline.deactivated', ['guideline_id' => $version->brand_guideline_id, 'version_id' => $version->id], $actor);
        });
    }
}
