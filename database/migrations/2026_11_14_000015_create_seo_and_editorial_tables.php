<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('canonical_url')->nullable()->after('meta_description');
            $table->string('robots_directive', 80)->nullable()->after('canonical_url');
            $table->text('seo_content')->nullable()->after('robots_directive');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('canonical_url')->nullable()->after('meta_description');
            $table->string('robots_directive', 80)->nullable()->after('canonical_url');
            $table->text('seo_content')->nullable()->after('robots_directive');
        });
        Schema::create('seo_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name')->default('نینی‌استوری');
            $table->string('tagline')->default('قصه‌های قشنگ کوچولوها');
            $table->string('default_title')->nullable();
            $table->string('default_description', 320)->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_logo_path')->nullable();
            $table->string('search_console_verification')->nullable();
            $table->string('analytics_measurement_id')->nullable();
            $table->boolean('indexing_enabled')->default(false);
            $table->timestamps();
        });
        Schema::create('article_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->text('content');
            $table->string('cover_image_path')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots_directive', 80)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });
        Schema::create('seo_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path')->unique();
            $table->string('target_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('article_categories');
        Schema::dropIfExists('seo_settings');
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['canonical_url', 'robots_directive', 'seo_content']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['canonical_url', 'robots_directive', 'seo_content']));
    }
};
