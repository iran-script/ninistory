<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('subtitle', 500)->nullable();
            $table->string('desktop_image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('alt_text', 255);
            $table->string('cta_label', 120)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('position', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['position', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
