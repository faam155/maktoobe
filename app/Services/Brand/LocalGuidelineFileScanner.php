<?php

namespace App\Services\Brand;

use App\Contracts\GuidelineFileScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class LocalGuidelineFileScanner implements GuidelineFileScanner
{
    public function assertSafe(UploadedFile $file): void
    {
        if (! app()->environment(['local', 'testing', 'browser'])) {
            throw ValidationException::withMessages(['file' => __('brand.scanner_unavailable')]);
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
            throw ValidationException::withMessages(['file' => __('brand.file_unsafe')]);
        }
    }
}
