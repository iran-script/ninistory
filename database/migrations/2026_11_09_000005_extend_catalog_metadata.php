<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique();
            $table->string('short_description', 500)->nullable();
            $table->string('brand')->nullable();
            $table->string('material')->nullable();
            $table->string('age_guidance')->nullable();
            $table->json('size_chart')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('compare_at_price_toman')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unique(['product_id', 'size', 'color_name'], 'product_variant_combination_unique');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('caption')->nullable();
            $table->boolean('is_cover')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('product_images', fn (Blueprint $table) => $table->dropColumn(['caption', 'is_cover']));
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variant_combination_unique');
            $table->dropColumn(['compare_at_price_toman', 'image_path', 'weight_grams']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'short_description', 'brand', 'material', 'age_guidance', 'size_chart', 'meta_title', 'meta_description']);
        });
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['image_path', 'meta_title', 'meta_description']));
    }
};
