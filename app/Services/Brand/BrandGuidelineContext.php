<?php

namespace App\Services\Brand;

use App\Models\BrandGuidelineVersion;

class BrandGuidelineContext
{
    public function activeVersion(): ?BrandGuidelineVersion
    {
        return BrandGuidelineVersion::with('guideline')->where('is_active', true)->latest('activated_at')->first();
    }

    public function forVersion(?BrandGuidelineVersion $version): ?string
    {
        if (! $version || $version->scan_status !== 'clean' || $version->extraction_status !== 'ready' || blank($version->extracted_text)) {
            return null;
        }

        return mb_substr("Brand guideline: {$version->guideline->title} (version {$version->version})\n{$version->extracted_text}", 0, 12000);
    }
}
