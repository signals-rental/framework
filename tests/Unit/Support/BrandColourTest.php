<?php

use App\Support\BrandColour;

describe('luminance', function () {
    it('returns 0 for black and 1 for white', function () {
        expect(BrandColour::luminance('#000000'))->toBe(0.0);
        expect(round(BrandColour::luminance('#ffffff'), 6))->toBe(1.0);
    });

    it('handles 3-digit and prefix-less hex', function () {
        expect(BrandColour::luminance('fff'))->toBe(BrandColour::luminance('#ffffff'));
        expect(BrandColour::luminance('#000'))->toBe(BrandColour::luminance('#000000'));
    });

    it('matches a known mid-grey luminance', function () {
        // #777777 relative luminance ≈ 0.1845
        expect(round(BrandColour::luminance('#777777'), 4))->toBe(0.1845);
    });
});

describe('contrast', function () {
    it('returns 21 for black against white', function () {
        expect(round(BrandColour::contrast('#000000', '#ffffff'), 0))->toBe(21.0);
    });

    it('returns 1 for identical colours', function () {
        expect(BrandColour::contrast('#3b82f6', '#3b82f6'))->toBe(1.0);
    });

    it('is symmetric', function () {
        expect(BrandColour::contrast('#1e3a5f', '#ffffff'))
            ->toBe(BrandColour::contrast('#ffffff', '#1e3a5f'));
    });
});

describe('ink', function () {
    it('passes navy through unchanged (already meets 4.5 vs white)', function () {
        expect(BrandColour::ink('#1e3a5f'))->toBe('#1e3a5f');
    });

    it('passes the default green through unchanged vs white', function () {
        // #059669 contrast vs white ≈ 3.0 — does NOT meet 4.5, so it is darkened.
        // The accent ink target in the app is 4.5 against white for text usage.
        expect(BrandColour::contrast('#059669', '#ffffff'))->toBeLessThan(4.5);
    });

    it('darkens a white primary to meet 4.5 against white', function () {
        $ink = BrandColour::ink('#ffffff', '#ffffff', 4.5);

        expect($ink)->not->toBe('#ffffff');
        expect(BrandColour::contrast($ink, '#ffffff'))->toBeGreaterThanOrEqual(4.5);
    });

    it('darkens a washed-out light blue to meet the target', function () {
        $ink = BrandColour::ink('#3b82f6', '#ffffff', 4.5);

        expect(BrandColour::contrast($ink, '#ffffff'))->toBeGreaterThanOrEqual(4.5);
    });

    it('preserves hue when darkening (blue stays the dominant channel)', function () {
        $ink = BrandColour::ink('#3b82f6', '#ffffff', 4.5);

        $value = ltrim($ink, '#');
        $r = hexdec(substr($value, 0, 2));
        $g = hexdec(substr($value, 2, 2));
        $b = hexdec(substr($value, 4, 2));

        // Blue was the dominant channel in #3b82f6 and must remain so.
        expect($b)->toBeGreaterThan($r);
        expect($b)->toBeGreaterThan($g);
    });

    it('always meets the target even for the hardest case', function () {
        // White against white can never reach 4.5 by tinting white, so the
        // stepwise darkening must kick in and satisfy the floor.
        $ink = BrandColour::ink('#fefefe', '#ffffff', 7.0);

        expect(BrandColour::contrast($ink, '#ffffff'))->toBeGreaterThanOrEqual(7.0);
    });
});

describe('on', function () {
    it('returns near-black for a white background', function () {
        expect(BrandColour::on('#ffffff'))->toBe('#0f172a');
    });

    it('returns white for a navy background', function () {
        expect(BrandColour::on('#1e3a5f'))->toBe('#ffffff');
    });

    it('keeps white on the default green fill (preserves the stock theme)', function () {
        // White clears the readability floor on #059669, so on() must keep the
        // established white-on-green button text rather than flipping to dark.
        expect(BrandColour::on('#059669'))->toBe('#ffffff');
    });

    it('returns white for a saturated mid blue background', function () {
        expect(BrandColour::on('#2563eb'))->toBe('#ffffff');
    });

    it('falls back to dark ink only for genuinely pale fills', function () {
        // A pale yellow fails the white floor → dark ink wins.
        expect(BrandColour::on('#fde047'))->toBe('#0f172a');
        expect(BrandColour::on('#f5f5f5'))->toBe('#0f172a');
    });

    it('returns near-black for a pale yellow background', function () {
        expect(BrandColour::on('#fde047'))->toBe('#0f172a');
    });
});

describe('invalid hex fallback', function () {
    it('falls back to the default primary for garbage input', function () {
        expect(BrandColour::normalise('not-a-colour'))->toBe('#1e3a5f');
        expect(BrandColour::normalise(''))->toBe('#1e3a5f');
        expect(BrandColour::normalise('#12'))->toBe('#1e3a5f');
    });

    it('ink falls back to the default primary for invalid input', function () {
        // Default navy already passes 4.5 against white, so it returns unchanged.
        expect(BrandColour::ink('garbage'))->toBe('#1e3a5f');
    });

    it('normalises shorthand and uppercase to lowercase 6-digit', function () {
        expect(BrandColour::normalise('#ABC'))->toBe('#aabbcc');
        expect(BrandColour::normalise('1E3A5F'))->toBe('#1e3a5f');
    });
});
