<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable();
            $table->boolean('is_active')->default(true); $table->unsignedSmallInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); $table->string('slug')->unique(); $table->text('description')->nullable(); $table->text('care_instructions')->nullable();
            $table->boolean('is_active')->default(false); $table->boolean('is_featured')->default(false); $table->timestamps(); $table->index(['is_active', 'is_featured']);
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->string('sku')->unique();
            $table->string('size', 40); $table->string('color_name', 80); $table->char('color_hex', 7)->nullable();
            $table->unsignedBigInteger('price_toman'); $table->unsignedInteger('stock_quantity')->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->index(['product_id', 'size', 'color_name']);
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->string('path');
            $table->string('alt_text'); $table->unsignedSmallInteger('sort_order')->default(0); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_images'); Schema::dropIfExists('product_variants'); Schema::dropIfExists('products'); Schema::dropIfExists('categories');
    }
};
