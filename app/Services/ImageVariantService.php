<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageVariantService
{
    /**
     * @return array{width: int|null, height: int|null, variants: array<int, string>}
     */
    public function generate(string $path, array $widths = []): array
    {
        $disk = Storage::disk('public');
        $contents = $disk->get($path);
        $source = @imagecreatefromstring($contents);
        $dimensions = @getimagesizefromstring($contents);

        if (! $source || ! $dimensions || ! function_exists('imagewebp')) {
            return ['width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null, 'variants' => []];
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

            if ($encoded !== false) {
                $disk->put($variantPath, $encoded);
                $variants[$width] = $variantPath;
            }
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
        $path = $file->store($directory, 'public');

        return ['path' => $path] + $this->generate($path);
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
