<?php

use App\Support\LogoTransparency;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Encodes an Intervention image to a temp file and wraps it in a non-test
 * UploadedFile so LogoTransparency reads real pixel data.
 */
function uploadedFromImage(ImageInterface $image, string $format, string $name): UploadedFile
{
    $encoded = match ($format) {
        'png' => $image->toPng(),
        'jpg' => $image->toJpeg(),
        'webp' => $image->toWebp(),
        default => throw new InvalidArgumentException("Unsupported format: {$format}"),
    };

    $path = tempnam(sys_get_temp_dir(), 'logo_').'.'.$format;
    file_put_contents($path, (string) $encoded);

    return new UploadedFile($path, $name, $encoded->mediaType(), null, true);
}

it('detects a fully opaque PNG as opaque', function () {
    $image = Image::create(48, 48)->fill('#1e3a5f');

    expect(LogoTransparency::detect(uploadedFromImage($image, 'png', 'opaque.png')))->toBeFalse();
});

it('detects a PNG with an alpha region as transparent', function () {
    // A freshly created Intervention canvas is fully transparent; draw an opaque
    // square covering only part of it so transparent pixels remain.
    $image = Image::create(48, 48);
    $image->drawRectangle(0, 0, function ($rect) {
        $rect->size(20, 20);
        $rect->background('#1e3a5f');
    });

    expect(LogoTransparency::detect(uploadedFromImage($image, 'png', 'alpha.png')))->toBeTrue();
});

it('treats a JPEG as always opaque', function () {
    $image = Image::create(48, 48)->fill('#1e3a5f');

    expect(LogoTransparency::detect(uploadedFromImage($image, 'jpg', 'photo.jpg')))->toBeFalse();
});

it('treats an SVG as transparent without scanning', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" fill="#1e3a5f"/></svg>';
    $path = tempnam(sys_get_temp_dir(), 'logo_').'.svg';
    file_put_contents($path, $svg);

    $file = new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true);

    expect(LogoTransparency::detect($file))->toBeTrue();
});
