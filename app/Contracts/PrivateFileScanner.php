<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface PrivateFileScanner
{
    public function assertSafe(UploadedFile $file): void;
}
