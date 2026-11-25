<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImageVariantService
{
    /**
     * @return array{width: int|null, height: int|null, variants: array<int, string>}
     */
    public function generate(string $path, array $widths = []): array
    {
        $disk = Storage::disk('public');
        $contents = $disk->get($path);
        if (! function_exists('getimagesizefromstring')) {
            return ['width' => null, 'height' => null, 'variants' => []];
        }

        $dimensions = @getimagesizefromstring($contents);

        if (! $dimensions) {
            throw new RuntimeException('The uploaded image could not be read after storage.');
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return ['width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null, 'variants' => []];
        }

        $source = @imagecreatefromstring($contents);
        if (! $source) {
            throw new RuntimeException('The uploaded image could not be decoded after storage.');
        }

        $widths = $widths ?: config('performance.image_variant_widths', [400, 800, 1200]);
        $variants = [];
        $sourceWidth = (int) $dimensions[0];
        $sourceHeight = (int) $dimensions[1];

        foreach ($widths as $width) {
            $width = (int) $width;
            if ($width < 1 || $width >= $sourceWidth) {
                continue;
            }

            $height = (int) round($sourceHeight * ($width / $sourceWidth));
            $target = imagecreatetruecolor($width, $height);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            $variantPath = $this->variantPath($path, $width);
            ob_start();
            imagewebp($target, null, 82);
            $encoded = ob_get_clean();
            imagedestroy($target);

            if ($encoded === false || ! $disk->put($variantPath, $encoded)) {
                throw new RuntimeException('Unable to store a generated product image variant.');
            }

            $variants[$width] = $variantPath;
        }

        imagedestroy($source);

        return ['width' => $sourceWidth, 'height' => $sourceHeight, 'variants' => $variants];
    }

    /**
     * @return array<int, string>
     */
    public function responsiveSources(string $path): array
    {
        $disk = Storage::disk('public');
        $sources = [];

        foreach (config('performance.image_variant_widths', [400, 800, 1200]) as $width) {
            $variantPath = $this->variantPath($path, (int) $width);
            if ($disk->exists($variantPath)) {
                $sources[(int) $width] = $disk->url($variantPath);
            }
        }

        return $sources;
    }

    public function store(UploadedFile $file, string $directory): array
    {
        $path = null;
        try {
            $path = $file->store($directory, 'public');
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Unable to store the uploaded product image.');
            }

            return ['path' => $path] + $this->generate($path);
        } catch (\Throwable $exception) {
            report($exception);
            if (is_string($path) && $path !== '') {
                try {
                    $this->delete($path);
                } catch (\Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw ValidationException::withMessages(['images' => 'ذخیره یا آماده‌سازی یکی از تصاویر انجام نشد. تصویر را دوباره انتخاب کنید و دوباره تلاش کنید.']);
        }
    }

    public function delete(string $path): void
    {
        $disk = Storage::disk('public');
        $disk->delete($path);

        foreach (config('performance.image_variant_widths', [400, 800, 1200]) as $width) {
            $disk->delete($this->variantPath($path, (int) $width));
        }
    }

    public function variantPath(string $path, int $width): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $withoutExtension = substr($path, 0, -(strlen($extension) + 1));

        return $withoutExtension.'-'.$width.'.webp';
    }
}
