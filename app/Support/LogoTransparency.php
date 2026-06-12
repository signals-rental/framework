<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Colors\Rgb\Channels\Alpha;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Detects whether an uploaded logo image contains alpha transparency.
 *
 * Used to decide whether the header should wrap the logo in an opaque white
 * "plate" (for logos that would otherwise be illegible against a coloured
 * header) or render it bare (for transparent logos designed to sit on any
 * background).
 */
final class LogoTransparency
{
    /**
     * Returns true when the uploaded image contains at least one pixel with
     * alpha below fully-opaque.
     *
     * - SVG is treated as transparent (vector — cannot be raster-scanned).
     * - JPEG is always opaque (the format has no alpha channel).
     * - PNG/WebP/GIF are downscaled to ~64px and sampled for any alpha < 255.
     * - On any decoding error we assume opaque (the safe default that still
     *   yields a legible plate in the header).
     */
    public static function detect(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if ($extension === 'svg' || str_contains($mime, 'svg')) {
            return true;
        }

        if (in_array($extension, ['jpg', 'jpeg'], true) || str_contains($mime, 'jpeg')) {
            return false;
        }

        try {
            $image = Image::read($file->getRealPath() ?: $file->getContent());
            $image->scaleDown(64, 64);

            $width = $image->width();
            $height = $image->height();

            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    if ($image->pickColor($x, $y)->channel(Alpha::class)->value() < 255) {
                        return true;
                    }
                }
            }

            return false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
