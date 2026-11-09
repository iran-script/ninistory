<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    public function index()
    {
        return view('admin.banners.index', [
            'banners' => Banner::orderBy('position')->orderBy('sort_order')->orderByDesc('id')->paginate(20),
            'positions' => Banner::POSITIONS,
        ]);
    }

    public function create()
    {
        return view('admin.banners.form', ['banner' => new Banner(['is_active' => true]), 'positions' => Banner::POSITIONS]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $paths = $this->storeImages($request);
        Banner::create($data + $paths);

        return redirect()->route('admin.banners.index')->with('status', 'بنر با موفقیت ساخته شد.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.form', ['banner' => $banner, 'positions' => Banner::POSITIONS]);
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validated($request, $banner);
        $oldPaths = [];
        $paths = [];

        if ($request->hasFile('desktop_image')) {
            $oldPaths[] = $banner->desktop_image_path;
            $paths['desktop_image_path'] = $this->storeImage($request->file('desktop_image'));
        }
        if ($request->hasFile('mobile_image')) {
            $oldPaths[] = $banner->mobile_image_path;
            $paths['mobile_image_path'] = $this->storeImage($request->file('mobile_image'));
        }

        DB::transaction(fn () => $banner->update($data + $paths));
        foreach (array_filter($oldPaths) as $path) Storage::disk('public')->delete($path);

        return redirect()->route('admin.banners.index')->with('status', 'بنر به‌روزرسانی شد.');
    }

    public function destroy(Banner $banner)
    {
        $paths = [$banner->desktop_image_path, $banner->mobile_image_path];
        $banner->delete();
        foreach (array_filter($paths) as $path) Storage::disk('public')->delete($path);

        return back()->with('status', 'بنر حذف شد.');
    }

    private function validated(Request $request, ?Banner $banner = null): array
    {
        $imageRules = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=300,max_width=5000,max_height=5000'];
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'desktop_image' => [$banner?->exists ? 'sometimes' : 'required', 'file', ...$imageRules],
            'mobile_image' => ['nullable', 'file', ...$imageRules],
            'alt_text' => ['required', 'string', 'max:255'],
            'cta_label' => ['nullable', 'string', 'max:120'],
            'cta_url' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && ! preg_match('/^(?:\/(?!\/)|#)/', $value)) {
                    $fail('لینک فراخوان باید به یک مسیر داخلی سایت یا بخش همین صفحه اشاره کند.');
                }
            }],
            'position' => ['required', Rule::in(array_keys(Banner::POSITIONS))],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function storeImages(Request $request): array
    {
        $paths = ['desktop_image_path' => $this->storeImage($request->file('desktop_image'))];
        if ($request->hasFile('mobile_image')) $paths['mobile_image_path'] = $this->storeImage($request->file('mobile_image'));

        return $paths;
    }

    private function storeImage($file): string
    {
        return $file->store('banners', 'public');
    }
}
