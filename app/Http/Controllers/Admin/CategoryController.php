<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SeoRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', ['categories' => Category::with('parent')->withCount('products')->orderBy('sort_order')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category, 'parents' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'category-'.Str::lower(Str::random(10)));
        $category = Category::create($data);
        $this->saveImage($request, $category);

        return redirect()->route('admin.categories.index')->with('status', 'دسته‌بندی ساخته شد.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => Category::whereKeyNot($category->id)->orderBy('name')->get()]);
    }

    public function update(Request $request, Category $category)
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
        if ($oldSlug !== $category->slug) {
            SeoRedirect::updateOrCreate(['source_path' => 'categories/'.$oldSlug], ['target_url' => route('categories.show', $category), 'status_code' => 301]);
        }

        return redirect()->route('admin.categories.index')->with('status', 'دسته‌بندی به‌روزرسانی شد.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => 'دسته‌بندی دارای محصول یا زیرشاخه است و حذف نمی‌شود.']);
        }
        $category->delete();

        return back()->with('status', 'دسته‌بندی حذف شد.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'slug' => ['nullable', 'string', 'max:180', 'alpha_dash:ascii', 'unique:categories,slug,'.($category?->id ?? 'NULL')], 'parent_id' => ['nullable', 'integer', 'exists:categories,id', 'not_in:'.($category?->id ?? '')], 'description' => ['nullable', 'string', 'max:5000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_active' => ['sometimes', 'boolean'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320'], 'canonical_url' => ['nullable', 'url:http,https', 'max:500'], 'robots_directive' => ['nullable', 'in:index,follow,noindex,follow,noindex,nofollow'], 'seo_content' => ['nullable', 'string', 'max:10000'], 'remove_image' => ['sometimes', 'boolean'], 'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=400,min_height=300,max_width=5000,max_height=5000']]);
        $data['is_active'] = $request->boolean('is_active');
        $data['parent_id'] = $data['parent_id'] ?? null;
        unset($data['remove_image'], $data['image']);

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
}
