<?php

namespace App\Services\Events;

use App\Services\Brand\GuidelineFileInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class EventFileInspector
{
    public function inspect(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        // Limit archive work and reject macros, embedded binaries and traversal entries.
        if ($extension === 'docx') {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->invalid();
            }
            try {
                $total = 0;
                if ($zip->numFiles > 500) {
                    $this->invalid();
                }
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i);
                    $name = str_replace('\\', '/', $entry['name']);
                    $total += $entry['size'];
                    if ($total > 20 * 1024 * 1024 || preg_match('~(^/|(^|/)\.\.(/|$)|:|\.bin$|\.exe$)~i', $name) || ($entry['encryption_method'] ?? 0) !== 0) {
                        $this->invalid();
                    }
                }
            } finally {
                $zip->close();
            }
        }
        try {
            $inspected = app(GuidelineFileInspector::class)->inspect($file);
        } catch (ValidationException) {
            $this->invalid();
        }
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $dimensions = @getimagesize($file->getRealPath());
            if (! $dimensions || $dimensions[0] > 8000 || $dimensions[1] > 8000 || $dimensions[0] * $dimensions[1] > 24000000) {
                $this->invalid();
            }
        }
        if ($extension === 'txt' && ($inspected['mime_type'] !== 'text/plain' || preg_match('/<\?(?:php|=)|<\s*(?:html|script|svg)\b/i', file_get_contents($file->getRealPath())))) {
            $this->invalid();
        }

        return ['extension' => $inspected['extension'], 'mime_type' => $inspected['mime_type']];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['files' => __('event_files.invalid')]);
    }
}
