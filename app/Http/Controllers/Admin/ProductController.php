<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')->withCount('variants')->when($request->filled('q'), fn ($q) => $q->where(fn ($query) => $query->where('name', 'like', '%'.$request->input('q').'%')->orWhere('sku', 'like', '%'.$request->input('q').'%')->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', '%'.$request->input('q').'%'))))->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))->when($request->input('status') === 'published', fn ($q) => $q->where('is_active', true))->when($request->input('status') === 'draft', fn ($q) => $q->where('is_active', false))->latest()->paginate(20)->withQueryString();
        return view('admin.products.index', ['products' => $products, 'categories' => Category::orderBy('name')->get()]);
    }

    public function create() { return view('admin.products.form', ['product' => new Product, 'categories' => Category::where('is_active', true)->orderBy('name')->get()]); }
    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $product = DB::transaction(function () use ($data) { $product = Product::create($data); $this->saveVariants(request(), $product); return $product; });
        $this->saveImages($request, $product);
        return redirect()->route('admin.products.edit', $product)->with('status', 'محصول ساخته شد.');
    }

    public function edit(Product $product) { $product->load(['variants', 'images']); return view('admin.products.form', ['product' => $product, 'categories' => Category::where('is_active', true)->orderBy('name')->get()]); }
    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product);
        DB::transaction(function () use ($request, $data, $product) { $product->update($data); $this->saveVariants($request, $product); });
        $this->saveImages($request, $product);
        return back()->with('status', 'تغییرات محصول ذخیره شد.');
    }

    public function destroy(Product $product)
    {
        if ($product->variants()->whereHas('orderItems')->exists()) return back()->withErrors(['product' => 'محصول دارای سابقه سفارش است و حذف نمی‌شود. آن را پیش‌نویس کنید.']);
        DB::transaction(function () use ($product) { foreach ($product->images as $image) Storage::disk('public')->delete($image->path); $product->delete(); });
        return redirect()->route('admin.products.index')->with('status', 'محصول حذف شد.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        DB::transaction(function () use ($image) { Storage::disk('public')->delete($image->path); $image->delete(); });
        return back()->with('status', 'تصویر حذف شد.');
    }

    public function stock(Request $request, ProductVariant $variant)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:1000000'], 'reason' => ['required', 'string', 'min:3', 'max:180']]);
        DB::transaction(function () use ($request, $variant, $data) {
            $locked = ProductVariant::lockForUpdate()->findOrFail($variant->id);
            $before = $locked->stock_quantity; $after = (int) $data['quantity'];
            $locked->update(['stock_quantity' => $after]);
            if ($before !== $after) $locked->inventoryMovements()->create(['user_id' => $request->user()->id, 'quantity_delta' => $after - $before, 'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'admin_adjustment', 'reference' => $data['reason']]);
        });
        return back()->with('status', 'موجودی و سابقه‌ی تغییر ثبت شد.');
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $data = $request->validate(['category_id' => ['nullable', 'integer', 'exists:categories,id'], 'name' => ['required', 'string', 'max:180'], 'slug' => ['nullable', 'string', 'max:190', 'alpha_dash:ascii', 'unique:products,slug,'.($product?->id ?? 'NULL')], 'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,'.($product?->id ?? 'NULL')], 'short_description' => ['nullable', 'string', 'max:500'], 'description' => ['nullable', 'string', 'max:10000'], 'brand' => ['nullable', 'string', 'max:160'], 'material' => ['nullable', 'string', 'max:160'], 'care_instructions' => ['nullable', 'string', 'max:5000'], 'age_guidance' => ['nullable', 'string', 'max:160'], 'size_chart' => ['nullable', 'string', 'max:10000'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320'], 'is_active' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean']]);
        if (($data['is_active'] ?? false) && count($request->input('variants', [])) < 1) $request->validate(['variants' => ['required', 'array', 'min:1']]);
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'product-'.Str::lower(Str::random(10)));
        $data['is_active'] = $request->boolean('is_active'); $data['is_featured'] = $request->boolean('is_featured');
        $data['size_chart'] = filled($data['size_chart'] ?? null) ? json_decode($data['size_chart'], true, 512, JSON_THROW_ON_ERROR) : null;
        return $data;
    }

    private function saveVariants(Request $request, Product $product): void
    {
        foreach ($request->input('variants', []) as $id => $row) {
            $row = validator($row, ['sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku,'.$id], 'size' => ['required', 'string', 'max:40'], 'color_name' => ['required', 'string', 'max:80'], 'color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'price_toman' => ['required', 'integer', 'min:1'], 'compare_at_price_toman' => ['nullable', 'integer', 'gt:price_toman'], 'is_active' => ['sometimes', 'boolean']])->validate();
            $duplicate = $product->variants()->where('size', $row['size'])->where('color_name', $row['color_name'])->when(is_numeric($id), fn ($q) => $q->whereKeyNot($id))->exists();
            if ($duplicate) throw \Illuminate\Validation\ValidationException::withMessages(['variants' => 'ترکیب رنگ و اندازه برای این محصول تکراری است.']);
            $variant = $product->variants()->whereKey(is_numeric($id) ? $id : 0)->first();
            if ($variant) { $variant->update(collect($row)->except(['stock_quantity'])->all() + ['is_active' => (bool) ($row['is_active'] ?? false)]); continue; }
            $product->variants()->create($row + ['stock_quantity' => 0, 'is_active' => (bool) ($row['is_active'] ?? false)]);
        }
    }

    private function saveImages(Request $request, Product $product): void
    {
        foreach ($request->file('images', []) as $index => $file) {
            $validated = validator(['image' => $file], ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']])->validate();
            $path = $validated['image']->store('products/'.$product->id, 'public');
            $product->images()->create(['path' => $path, 'alt_text' => $request->input("image_alt.$index", $product->name), 'caption' => $request->input("image_caption.$index"), 'sort_order' => $product->images()->count(), 'is_cover' => $product->images()->doesntExist()]);
        }
    }
}
