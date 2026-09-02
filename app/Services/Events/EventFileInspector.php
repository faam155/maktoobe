<?php

namespace App\Services\Events;

use App\Services\Brand\GuidelineFileInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use ZipArchive;

class EventFileInspector
{
    public function inspect(UploadedFile $file): array
    {
        // Temporary uploads can disappear (for example, external quarantine)
        // after Laravel validation but before MIME inspection.
        if (! is_readable($file->getPathname())) {
            $this->invalid();
        }
        $extension = strtolower($file->getClientOriginalExtension());
        // Limit archive work and reject macros, embedded binaries and traversal entries.
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->invalid();
            }
            try {
                if ($extension === 'xlsx' && ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false)) {
                    $this->invalid();
                }
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
        if ($extension === 'xlsx') {
            $mime = (string) $file->getMimeType();
            if (! in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true)) {
                $this->invalid();
            }

            return ['extension' => 'xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }
        try {
            $inspected = app(GuidelineFileInspector::class)->inspect($file);
        } catch (ValidationException|InvalidArgumentException) {
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
