<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SeoRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->withCount(['products', 'children'])->orderBy('sort_order')->orderBy('name')->get();
        $tree = $this->tree($categories);

        return view('admin.categories.index', compact('categories', 'tree'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category, 'parents' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'category-'.Str::lower(Str::random(10)));
        $category = Category::create($data);
        $this->saveImage($request, $category);
        $this->saveSizeGuide($request, $category);

        return redirect()->route('admin.categories.index')->with('status', 'دسته‌بندی ساخته شد.');
    }

    public function edit(Category $category): View
    {
        $excluded = $category->descendants()->push($category->id);

        return view('admin.categories.form', ['category' => $category, 'parents' => Category::whereNotIn('id', $excluded)->orderBy('name')->get()]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $oldSlug = $category->slug;
        $data = $this->validated($request, $category);
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'category-'.Str::lower(Str::random(10)));
        if ($data['parent_id'] && $category->descendants()->contains((int) $data['parent_id'])) {
            return back()->withErrors(['parent_id' => 'انتخاب این والد باعث ایجاد چرخه می‌شود.'])->withInput();
        }
        $category->update($data);
        if ($request->boolean('remove_image') && $category->image_path) {
            Storage::disk('public')->delete($category->image_path);
            $category->update(['image_path' => null]);
        }
        $this->saveImage($request, $category);
        $this->saveSizeGuide($request, $category);
        if ($oldSlug !== $category->slug) {
            SeoRedirect::updateOrCreate(['source_path' => 'categories/'.$oldSlug], ['target_url' => route('categories.show', $category), 'status_code' => 301]);
        }

        return redirect()->route('admin.categories.index')->with('status', 'دسته‌بندی به‌روزرسانی شد.');
    }

    public function impact(Category $category): JsonResponse
    {
        $descendantIds = $category->descendants();
        $descendantProducts = $descendantIds->isEmpty() ? 0 : Category::query()->whereIn('id', $descendantIds)->withCount('products')->get()->sum('products_count');

        return response()->json([
            'category_id' => $category->id,
            'direct_products' => $category->products()->count(),
            'descendant_categories' => $descendantIds->count(),
            'descendant_products' => $descendantProducts,
            'homepage_slots' => $category->homepageCategorySlots()->count(),
            'can_delete' => ! $category->products()->exists() && $descendantIds->isEmpty() && ! $category->homepageCategorySlots()->exists(),
        ]);
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['categories' => ['required', 'array', 'min:1'], 'categories.*.id' => ['required', 'integer', 'distinct', 'exists:categories,id'], 'categories.*.parent_id' => ['nullable', 'integer', 'exists:categories,id'], 'categories.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535']]);
        foreach ($data['categories'] as $row) {
            $parentId = filled($row['parent_id'] ?? null) ? (int) $row['parent_id'] : null;
            if ($parentId !== null && ((int) $row['id'] === $parentId || Category::findOrFail((int) $row['id'])->descendants()->contains($parentId))) {
                return back()->withErrors(['categories' => 'ساختار دسته‌بندی نمی‌تواند چرخه ایجاد کند.'])->withInput();
            }
        }

        DB::transaction(function () use ($data): void {
            $categories = Category::query()->whereIn('id', collect($data['categories'])->pluck('id'))->lockForUpdate()->get()->keyBy('id');
            foreach ($data['categories'] as $row) {
                $categories[(int) $row['id']]->update(['parent_id' => filled($row['parent_id'] ?? null) ? (int) $row['parent_id'] : null, 'sort_order' => (int) $row['sort_order']]);
            }
        });

        return back()->with('status', 'ترتیب دسته‌بندی‌ها ذخیره شد.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $descendantIds = $category->descendants();
        if ($category->products()->exists() || $descendantIds->isNotEmpty() || $category->homepageCategorySlots()->exists()) {
            return back()->withErrors(['category' => 'دسته‌بندی دارای محصول یا زیرشاخه است و حذف نمی‌شود.']);
        }
        $category->delete();

        return back()->with('status', 'دسته‌بندی حذف شد.');
    }

    /** @return array<int, array<string, mixed>> */
    private function tree(Collection $categories, ?int $parentId = null): array
    {
        return $categories->where('parent_id', $parentId)->map(function (Category $category) use ($categories): array {
            $children = $this->tree($categories, $category->id);

            return [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent_id' => $category->parent_id,
                'is_active' => (bool) $category->is_active,
                'sort_order' => (int) $category->sort_order,
                'direct_products' => (int) $category->products_count,
                'children_count' => count($children),
                'total_products' => (int) $category->products_count + collect($children)->sum('total_products'),
                'image_url' => $category->image_path ? Storage::disk('public')->url($category->image_path) : null,
                'edit_url' => route('admin.categories.edit', $category),
                'update_url' => route('admin.categories.update', $category),
                'delete_url' => route('admin.categories.destroy', $category),
                'children' => $children,
            ];
        })->values()->all();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'slug' => ['nullable', 'string', 'max:180', 'alpha_dash:ascii', 'unique:categories,slug,'.($category?->id ?? 'NULL')], 'parent_id' => ['nullable', 'integer', 'exists:categories,id', 'not_in:'.($category?->id ?? '')], 'description' => ['nullable', 'string', 'max:5000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_active' => ['sometimes', 'boolean'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320'], 'canonical_url' => ['nullable', 'url:http,https', 'max:500'], 'robots_directive' => ['nullable', 'in:index,follow,noindex,follow,noindex,nofollow'], 'seo_content' => ['nullable', 'string', 'max:10000'], 'primary_search_intent' => ['nullable', 'string', 'max:180'], 'buying_guide' => ['nullable', 'string', 'max:12000'], 'faqs' => ['nullable', 'json'], 'related_category_ids' => ['nullable', 'json'], 'editorial_review_status' => ['sometimes', 'in:draft,needs_review,approved'], 'size_guide_title' => ['nullable', 'string', 'max:180'], 'size_guide_instructions' => ['nullable', 'string', 'max:5000'], 'size_guide_notes' => ['nullable', 'string', 'max:5000'], 'size_guide_rows' => ['nullable', 'json'], 'size_guide_is_published' => ['sometimes', 'boolean'], 'remove_image' => ['sometimes', 'boolean'], 'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=400,min_height=300,max_width=5000,max_height=5000']]);
        $data['is_active'] = $request->boolean('is_active');
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['editorial_review_status'] = $data['editorial_review_status'] ?? $category?->editorial_review_status ?? 'draft';
        $data['faqs'] = filled($data['faqs'] ?? null) ? json_decode($data['faqs'], true, 512, JSON_THROW_ON_ERROR) : null;
        $data['related_category_ids'] = filled($data['related_category_ids'] ?? null) ? json_decode($data['related_category_ids'], true, 512, JSON_THROW_ON_ERROR) : null;
        unset($data['remove_image'], $data['image'], $data['size_guide_title'], $data['size_guide_instructions'], $data['size_guide_notes'], $data['size_guide_rows'], $data['size_guide_is_published']);

        return $data;
    }

    private function saveImage(Request $request, Category $category): void
    {
        if (! $request->hasFile('image')) {
            return;
        }
        $oldPath = $category->image_path;
        $path = $request->file('image')->store('categories', 'public');
        $category->update(['image_path' => $path]);
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function saveSizeGuide(Request $request, Category $category): void
    {
        if (! $request->filled('size_guide_title') || ! $request->filled('size_guide_rows')) {
            return;
        }

        $rows = json_decode($request->string('size_guide_rows'), true, 512, JSON_THROW_ON_ERROR);
        validator(['rows' => $rows], ['rows' => ['array', 'min:1']])->validate();
        $category->sizeGuides()->updateOrCreate(['category_id' => $category->id], ['title' => $request->string('size_guide_title'), 'instructions' => $request->input('size_guide_instructions'), 'notes' => $request->input('size_guide_notes'), 'rows' => $rows, 'is_published' => $request->boolean('size_guide_is_published')]);
    }
}
