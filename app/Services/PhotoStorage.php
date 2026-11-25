<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PhotoStorage
{
    public function store(UploadedFile $file): array
    {
        $source = @imagecreatefromstring(file_get_contents($file->getPathname()));
        if (!$source) {
            throw ValidationException::withMessages(['image' => 'This photograph could not be decoded.']);
        }

        if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getPathname())['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($source, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) { 3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
            if ($angle) {
                $source = imagerotate($source, $angle, 0);
            }
        }

        imagepalettetotruecolor($source);
        $filePath = 'photos/'.Str::uuid();
        try {
            foreach (['' => 1600, '-960' => 960, '-480' => 480] as $suffix => $max) {
                $ratio = min(1, $max / max(imagesx($source), imagesy($source)));
                $scaled = imagescale($source, max(1, (int) round(imagesx($source) * $ratio)), max(1, (int) round(imagesy($source) * $ratio)));
                if (!$scaled) {
                    throw new RuntimeException('Photo resize failed.');
                }
                ob_start();
                try {
                    $encoded = imagewebp($scaled, null, 83);
                    $contents = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                if (!$encoded || !$contents || !Storage::disk('public')->put($filePath.$suffix.'.webp', $contents)) {
                    throw new RuntimeException('Photo storage failed.');
                }
            }
        } catch (Throwable $exception) {
            $this->delete($filePath.'.webp');
            throw $exception;
        }

        return ['path' => $filePath.'.webp', 'width' => imagesx($source), 'height' => imagesy($source)];
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete([$path, str_replace('.webp', '-960.webp', $path), str_replace('.webp', '-480.webp', $path)]);
    }
}

