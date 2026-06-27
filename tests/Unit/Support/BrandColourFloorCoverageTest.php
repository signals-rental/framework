<?php

use App\Support\BrandColour;

describe('BrandColour::ink hard floor', function () {
    it('falls through to pure black when no candidate can meet the target against the surface', function () {
        // Against a BLACK surface, darkening a colour toward black only lowers the
        // contrast ratio, so no stepwise candidate can reach this high target. The
        // loop exhausts and the hard floor returns pure black.
        $ink = BrandColour::ink('#888888', '#000000', 15.0);

        expect($ink)->toBe('#000000');
    });

    it('still returns a candidate from the loop when the target is reachable', function () {
        // Sanity contrast: against black, #888888 itself already clears a low target,
        // so the floor is NOT exercised here (returns the input unchanged).
        expect(BrandColour::ink('#888888', '#000000', 1.5))->toBe('#888888');
    });
});
