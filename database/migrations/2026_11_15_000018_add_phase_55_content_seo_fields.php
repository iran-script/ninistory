<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('primary_search_intent')->nullable();
            $table->text('buying_guide')->nullable();
            $table->json('faqs')->nullable();
            $table->json('related_category_ids')->nullable();
            $table->string('editorial_review_status')->default('draft');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('composition')->nullable();
            $table->text('fit_information')->nullable();
            $table->string('season')->nullable();
            $table->text('selling_points')->nullable();
            $table->json('measured_dimensions')->nullable();
            $table->string('content_review_status')->default('draft');
            $table->timestamp('content_verified_at')->nullable();
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->string('editorial_status')->default('draft');
            $table->string('target_search_intent')->nullable();
            $table->foreignId('related_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('source_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::create('size_guides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->text('notes')->nullable();
            $table->json('rows');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('size_guides');
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropForeign(['related_category_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['editorial_status', 'target_search_intent', 'related_category_id', 'source_notes', 'reviewed_by', 'reviewed_at']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['composition', 'fit_information', 'season', 'selling_points', 'measured_dimensions', 'content_review_status', 'content_verified_at']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['primary_search_intent', 'buying_guide', 'faqs', 'related_category_ids', 'editorial_review_status']));
    }
};
