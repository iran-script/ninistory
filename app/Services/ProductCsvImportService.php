<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductCsvImportService
{
    /** @return array{rows: Collection, errors: Collection} */
    public function preview(string $path): array
    {
        $handle = fopen($path, 'rb');
        $headers = $handle ? fgetcsv($handle) : false;
        $required = ['product_name', 'category_slug', 'product_sku', 'variant_sku', 'color_name', 'size', 'price_toman', 'stock_quantity'];
        $errors = collect();
        $rows = collect();
        if (! is_array($headers) || array_diff($required, $headers) !== []) {
            return ['rows' => $rows, 'errors' => collect(['ستون‌های لازم: '.implode(', ', $required)])];
        }
        $seen = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $row = array_combine($headers, $values);
            $key = trim((string) ($row['variant_sku'] ?? ''));
            $rowErrors = collect();
            foreach ($required as $field) {
                if (blank($row[$field] ?? null)) {
                    $rowErrors->push("ردیف {$line}: {$field} الزامی است.");
                }
            }
            if (isset($seen[$key]) || Product::where('sku', $row['product_sku'] ?? '')->exists() || ProductVariant::where('sku', $key)->exists()) {
                $rowErrors->push("ردیف {$line}: SKU تکراری است و تغییر خودکار محصول موجود مجاز نیست.");
            }
            if (! Category::where('slug', $row['category_slug'] ?? '')->exists()) {
                $rowErrors->push("ردیف {$line}: دسته‌بندی پیدا نشد.");
            }
            try {
                $row['price_toman'] = Money::normalize($row['price_toman'] ?? null);
            } catch (\InvalidArgumentException) {
                $row['price_toman'] = null;
            }
            if (($row['price_toman'] ?? 0) < 1 || (int) ($row['stock_quantity'] ?? -1) < 0) {
                $rowErrors->push("ردیف {$line}: قیمت یا موجودی معتبر نیست.");
            }
            if ($rowErrors->isNotEmpty()) {
                $errors = $errors->merge($rowErrors);
            } else {
                $seen[$key] = true;
                $rows->push($row);
            }
        }
        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    public function import(Collection $rows): int
    {
        return DB::transaction(function () use ($rows): int {
            $count = 0;
            foreach ($rows->groupBy('product_sku') as $productSku => $variants) {
                $first = $variants->first();
                $product = Product::create(['category_id' => Category::where('slug', $first['category_slug'])->value('id'), 'name' => $first['product_name'], 'slug' => Str::slug($first['product_name']).'-'.strtolower($productSku), 'sku' => $productSku, 'description' => $first['description'] ?? null, 'material' => $first['material'] ?? null, 'composition' => $first['composition'] ?? null, 'is_active' => false, 'content_review_status' => 'draft']);
                foreach ($variants as $row) {
                    $variant = app(ProductVariantSkuService::class)->create($product, ['sku' => $row['variant_sku'], 'size' => $row['size'], 'color_name' => $row['color_name'], 'price_toman' => (int) $row['price_toman'], 'stock_quantity' => 0, 'is_active' => true]);
                    app(InventoryService::class)->setPhysicalStock($variant, (int) $row['stock_quantity'], 'CSV import');
                    $count++;
                }
            }

            return $count;
        });
    }
}
