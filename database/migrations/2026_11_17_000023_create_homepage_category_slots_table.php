<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_category_slots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('position')->unique();
            $table->foreignId('category_id')->nullable()->unique()->constrained('categories')->nullOnDelete();
            $table->string('title', 160)->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_category_slots');
    }
};
