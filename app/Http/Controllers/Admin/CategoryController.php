<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index() { return view('admin.categories.index', ['categories' => Category::with('parent')->withCount('products')->orderBy('sort_order')->paginate(20)]); }
    public function create() { return view('admin.categories.form', ['category' => new Category, 'parents' => Category::orderBy('name')->get()]); }
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'category-'.Str::lower(Str::random(10)));
        Category::create($data);
        return redirect()->route('admin.categories.index')->with('status', 'دسته‌بندی ساخته شد.');
    }
    public function edit(Category $category) { return view('admin.categories.form', ['category' => $category, 'parents' => Category::whereKeyNot($category->id)->orderBy('name')->get()]); }
    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'category-'.Str::lower(Str::random(10)));
        if ($data['parent_id'] && $category->descendants()->contains((int) $data['parent_id'])) return back()->withErrors(['parent_id' => 'انتخاب این والد باعث ایجاد چرخه می‌شود.'])->withInput();
        $category->update($data);
        return redirect()->route('admin.categories.index')->with('status', 'دسته‌بندی به‌روزرسانی شد.');
    }
    public function destroy(Category $category)
    {
        if ($category->products()->exists() || $category->children()->exists()) return back()->withErrors(['category' => 'دسته‌بندی دارای محصول یا زیرشاخه است و حذف نمی‌شود.']);
        $category->delete();
        return back()->with('status', 'دسته‌بندی حذف شد.');
    }
    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'slug' => ['nullable', 'string', 'max:180', 'alpha_dash:ascii', 'unique:categories,slug,'.($category?->id ?? 'NULL')], 'parent_id' => ['nullable', 'integer', 'exists:categories,id', 'not_in:'.($category?->id ?? '')], 'description' => ['nullable', 'string', 'max:5000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'is_active' => ['sometimes', 'boolean'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320']]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
