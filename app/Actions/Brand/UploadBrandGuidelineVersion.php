<?php

namespace App\Actions\Brand;

use App\Actions\Identity\RecordAccountAudit;
use App\Contracts\GuidelineFileScanner;
use App\Models\BrandGuideline;
use App\Models\BrandGuidelineVersion;
use App\Models\User;
use App\Services\Brand\GuidelineFileInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class UploadBrandGuidelineVersion
{
    public function __construct(private GuidelineFileInspector $inspector, private GuidelineFileScanner $scanner) {}

    public function handle(User $actor, array $input, ?BrandGuideline $guideline = null): BrandGuidelineVersion
    {
        Gate::forUser($actor)->authorize($guideline ? 'update' : 'create', $guideline ?? BrandGuideline::class);
        $rules = ['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'string', 'max:60', Rule::unique('brand_guideline_versions')->where('brand_guideline_id', $guideline?->id)],
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,docx,png,jpg,jpeg,webp,txt']];
        $data = Validator::make($input, $rules)->validate();
        /** @var UploadedFile $file */ $file = $data['file'];
        $inspected = $this->inspector->inspect($file);
        $this->scanner->assertSafe($file);
        $path = 'brand-guidelines/'.bin2hex(random_bytes(16)).'.'.$inspected['extension'];
        Storage::disk('local')->putFileAs('', $file, $path);
        try {
            return DB::transaction(function () use ($actor, $data, $guideline, $file, $path, $inspected) {
                if ($guideline) {
                    $guideline->update(['title' => trim($data['title']), 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null]);
                } else {
                    $guideline = BrandGuideline::create(['title' => trim($data['title']), 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null, 'created_by' => $actor->id]);
                }
                $version = $guideline->versions()->create($inspected + ['version' => trim($data['version']), 'storage_disk' => 'local', 'storage_path' => $path,
                    'original_name' => basename($file->getClientOriginalName()), 'file_size' => $file->getSize(), 'scan_status' => 'clean', 'uploaded_by' => $actor->id]);
                app(RecordAccountAudit::class)->handle($actor, 'brand_guideline.version_uploaded', ['guideline_id' => $guideline->id, 'version_id' => $version->id], $actor);

                return $version;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
