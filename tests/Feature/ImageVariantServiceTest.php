<?php

namespace Tests\Feature;

use App\Services\ImageVariantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageVariantServiceTest extends TestCase
{
    public function test_uploaded_original_is_preserved_and_webp_variants_are_generated(): void
    {
        Storage::fake('public');
        $service = app(ImageVariantService::class);

        $media = $service->store(UploadedFile::fake()->image('dress.jpg', 1200, 1500), 'products/1');

        Storage::disk('public')->assertExists($media['path']);
        Storage::disk('public')->assertExists($media['variants'][400]);
        Storage::disk('public')->assertExists($media['variants'][800]);
        $this->assertSame(1200, $media['width']);
        $this->assertSame(1500, $media['height']);
    }

    public function test_generated_variants_are_removed_without_deleting_unrelated_files(): void
    {
        Storage::fake('public');
        $service = app(ImageVariantService::class);
        $media = $service->store(UploadedFile::fake()->image('dress.jpg', 1200, 1500), 'products/1');
        Storage::disk('public')->put('products/1/keep.txt', 'keep');

        $service->delete($media['path']);

        Storage::disk('public')->assertMissing($media['path']);
        Storage::disk('public')->assertMissing($media['variants'][400]);
        Storage::disk('public')->assertMissing($media['variants'][800]);
        Storage::disk('public')->assertExists('products/1/keep.txt');
    }
}
