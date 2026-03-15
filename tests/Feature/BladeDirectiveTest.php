<?php

use Illuminate\Support\Facades\Blade;

describe('Permission Blade directives', function () {
    it('compiles @area directive', function () {
        $compiled = Blade::compileString("@area('members.access')");

        expect($compiled)->toContain("auth()->user()->can('members.access')");
        expect($compiled)->toContain('if(');
    });

    it('compiles @endarea directive', function () {
        $compiled = Blade::compileString('@endarea');

        expect($compiled)->toBe('<?php endif; ?>');
    });

    it('compiles @action directive', function () {
        $compiled = Blade::compileString("@action('members.create')");

        expect($compiled)->toContain("auth()->user()->can('members.create')");
        expect($compiled)->toContain('if(');
    });

    it('compiles @endaction directive', function () {
        $compiled = Blade::compileString('@endaction');

        expect($compiled)->toBe('<?php endif; ?>');
    });

    it('compiles @costs directive', function () {
        $compiled = Blade::compileString('@costs');

        expect($compiled)->toContain("auth()->user()->can('costs.view')");
        expect($compiled)->toContain('if(');
    });

    it('compiles @endcosts directive', function () {
        $compiled = Blade::compileString('@endcosts');

        expect($compiled)->toBe('<?php endif; ?>');
    });
});
