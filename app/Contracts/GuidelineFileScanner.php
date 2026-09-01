<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface GuidelineFileScanner
{
    public function assertSafe(UploadedFile $file): void;
}
