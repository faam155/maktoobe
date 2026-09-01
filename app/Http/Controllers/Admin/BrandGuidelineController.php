<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Brand\SetActiveBrandGuidelineVersion;
use App\Actions\Brand\UploadBrandGuidelineVersion;
use App\Models\BrandGuideline;
use App\Models\BrandGuidelineVersion;
use App\Queries\Brand\BrandGuidelineIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandGuidelineController
{
    public function index(Request $request, BrandGuidelineIndexQuery $query): View
    {
        return view('admin.brand-guidelines.index', ['guidelines' => $query->handle($request->user())]);
    }

    public function create(): View
    {
        Gate::authorize('create', BrandGuideline::class);

        return view('admin.brand-guidelines.create');
    }

    public function store(Request $request, UploadBrandGuidelineVersion $action): RedirectResponse
    {
        $version = $action->handle($request->user(), $request->all());

        return redirect()->route('admin.brand-guidelines.show', $version->brand_guideline_id)->with('status', __('brand.uploaded'));
    }

    public function show(BrandGuideline $brandGuideline): View
    {
        Gate::authorize('view', $brandGuideline);

        return view('admin.brand-guidelines.show', ['guideline' => $brandGuideline->load(['versions' => fn ($query) => $query->with('uploader')->latest()])]);
    }

    public function version(Request $request, BrandGuideline $brandGuideline, UploadBrandGuidelineVersion $action): RedirectResponse
    {
        $action->handle($request->user(), $request->all(), $brandGuideline);

        return back()->with('status', __('brand.uploaded'));
    }

    public function status(Request $request, BrandGuidelineVersion $version, SetActiveBrandGuidelineVersion $action): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $action->handle($request->user(), $version, (bool) $data['active']);

        return back()->with('status', $data['active'] ? __('brand.activated') : __('brand.deactivated'));
    }

    public function download(BrandGuidelineVersion $version): StreamedResponse
    {
        Gate::authorize('view', $version);
        abort_unless($version->scan_status === 'clean', 404);
        abort_unless(Storage::disk($version->storage_disk)->exists($version->storage_path), 404);

        return Storage::disk($version->storage_disk)->download($version->storage_path, $version->original_name, ['Content-Type' => $version->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
