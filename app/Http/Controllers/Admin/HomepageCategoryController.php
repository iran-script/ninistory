<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomepageCategorySlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomepageCategoryController extends Controller
{
    public function edit(): View
    {
        return view('admin.homepage-categories.edit', [
            'slots' => HomepageCategorySlot::with('category')->orderBy('position')->get()->keyBy('position'),
            'categories' => Category::query()->where('is_active', true)->with('parent')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slots' => ['required', 'array', 'size:4'],
            'slots.*.category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'slots.*.title' => ['nullable', 'string', 'max:160'],
            'slots.*.image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=400,min_height=300,max_width=5000,max_height=5000'],
        ]);

        $categoryIds = collect($data['slots'])->pluck('category_id')->filter()->map(fn (int|string $id): int => (int) $id);
        $activeCategoryCount = Category::query()->whereIn('id', $categoryIds)->where('is_active', true)->count();
        if ($categoryIds->count() !== $categoryIds->unique()->count()) {
            return back()->withErrors(['slots' => 'هر دسته فقط یک‌بار می‌تواند در جایگاه‌های صفحه اصلی انتخاب شود.'])->withInput();
        }
        if ($activeCategoryCount !== $categoryIds->unique()->count()) {
            return back()->withErrors(['slots' => 'فقط دسته‌های فعال را می‌توان در صفحه اصلی نمایش داد.'])->withInput();
        }

        $oldPaths = [];
        $newPaths = [];

        try {
            DB::transaction(function () use ($request, $data, &$oldPaths, &$newPaths): void {
                foreach (range(1, 4) as $position) {
                    $slotData = $data['slots'][$position] ?? ['category_id' => null, 'title' => null];
                    $slot = HomepageCategorySlot::query()->where('position', $position)->first();
                    if (! filled($slotData['category_id'] ?? null)) {
                        if ($slot?->image_path) {
                            $oldPaths[] = $slot->image_path;
                        }
                        $slot?->delete();

                        continue;
                    }

                    $attributes = [
                        'position' => $position,
                        'category_id' => (int) $slotData['category_id'],
                        'title' => filled($slotData['title'] ?? null) ? trim((string) $slotData['title']) : null,
                    ];
                    if ($request->hasFile("slots.{$position}.image")) {
                        $newPath = $request->file("slots.{$position}.image")->store('homepage-categories', 'public');
                        $newPaths[] = $newPath;
                        $attributes['image_path'] = $newPath;
                        if ($slot?->image_path) {
                            $oldPaths[] = $slot->image_path;
                        }
                    } elseif ($slot) {
                        $attributes['image_path'] = $slot->image_path;
                    }

                    HomepageCategorySlot::query()->updateOrCreate(['position' => $position], $attributes);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($newPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        foreach (array_unique($oldPaths) as $path) {
            Storage::disk('public')->delete($path);
        }

        return back()->with('status', 'دسته‌های صفحه اصلی ذخیره شدند.');
    }
}
