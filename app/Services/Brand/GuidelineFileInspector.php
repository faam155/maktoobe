<?php

namespace App\Services\Brand;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuidelineFileInspector
{
    /** @return array{extension:string,mime_type:string,extracted_text:?string,extraction_status:string} */
    public function inspect(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $path = $file->getRealPath();
        $head = file_get_contents($path, false, null, 0, 16) ?: '';
        $valid = match ($extension) {
            'pdf' => str_starts_with($head, '%PDF-') && $mime === 'application/pdf',
            'docx' => $this->isDocx($path) && in_array($mime, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'], true),
            'png' => str_starts_with($head, "\x89PNG\r\n\x1a\n") && $mime === 'image/png',
            'jpg', 'jpeg' => str_starts_with($head, "\xff\xd8\xff") && $mime === 'image/jpeg',
            'webp' => substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP' && $mime === 'image/webp',
            'txt' => $this->isText($path) && in_array($mime, ['text/plain', 'text/x-php', 'application/octet-stream'], true),
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['file' => __('brand.invalid_file')]);
        }

        $text = $extension === 'txt' ? file_get_contents($path) : null;

        return ['extension' => $extension, 'mime_type' => $mime,
            'extracted_text' => $text === null ? null : mb_substr($text, 0, 20000),
            'extraction_status' => $text === null ? 'not_supported' : 'ready'];
    }

    private function isDocx(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $valid;
    }

    private function isText(string $path): bool
    {
        $content = file_get_contents($path);

        return $content !== false && ! str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8');
    }
}
