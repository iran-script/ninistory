<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('merchant_verification_status')->default('pending');
            $table->foreignId('merchant_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merchant_verified_at')->nullable();
            $table->text('merchant_review_reason')->nullable();
            $table->index(['merchant_verification_status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['merchant_verified_by']);
            $table->dropIndex(['merchant_verification_status', 'is_active']);
            $table->dropColumn(['merchant_verification_status', 'merchant_verified_by', 'merchant_verified_at', 'merchant_review_reason']);
        });
    }
};
