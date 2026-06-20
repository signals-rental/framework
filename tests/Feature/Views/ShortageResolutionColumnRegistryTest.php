<?php

use App\Views\Column;
use App\Views\ShortageResolutionColumnRegistry;

/**
 * R-D master M17 — ShortageResolutionColumnRegistry must expose
 * cancellation_reason and notes so the Cancelled-state resolution view can
 * display and filter them.
 */
describe('ShortageResolutionColumnRegistry', function () {
    it('exposes cancellation_reason and notes columns', function () {
        $registry = new ShortageResolutionColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKeys(['cancellation_reason', 'notes']);

        expect($columns['cancellation_reason'])->toBeInstanceOf(Column::class)
            ->and($columns['cancellation_reason']->filterable)->toBeTrue()
            ->and($columns['notes']->filterable)->toBeTrue();
    });
});
