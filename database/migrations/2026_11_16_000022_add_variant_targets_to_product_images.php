<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('color_name', 80)->nullable()->index();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropIndex(['color_name']);
            $table->dropColumn('color_name');
        });
    }
};
