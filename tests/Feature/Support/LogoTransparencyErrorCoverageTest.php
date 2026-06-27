<?php

use App\Support\LogoTransparency;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\UploadedFile;

it('assumes opaque (false) and reports when the image cannot be decoded', function () {
    // Capture the report() call from the catch block via a mocked handler.
    $handler = $this->mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->once();
    $handler->shouldReceive('shouldReport')->andReturn(true)->byDefault();

    // A .png file containing non-image bytes: it is not short-circuited by the
    // svg/jpeg checks, so Image::read() is reached and throws — the catch block
    // reports the error and returns the safe opaque default.
    $path = tempnam(sys_get_temp_dir(), 'logo_').'.png';
    file_put_contents($path, 'this is definitely not a valid PNG');

    $file = new UploadedFile($path, 'broken.png', 'image/png', null, true);

    expect(LogoTransparency::detect($file))->toBeFalse();
});
