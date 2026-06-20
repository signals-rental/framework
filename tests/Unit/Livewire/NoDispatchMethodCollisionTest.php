<?php

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Bug #1 — Livewire `dispatch` method-name collision guard
|--------------------------------------------------------------------------
|
| A Volt/Livewire component that declares `public function dispatch(...)` shadows
| Livewire\Component::dispatch($event, ...$params). PHP rejects the incompatible
| signature override with a FatalError the moment the component class is built —
| which is exactly what crashed /opportunities/{id}/assets (the assets tab had a
| `dispatch(int $assetId)` action, since renamed to `dispatchAsset`).
|
| PHPStan does not analyse the anonymous Volt component class, so this lexical
| guard scans every component source for a public `dispatch(` declaration. Reserved
| Livewire method names (dispatch, dispatchTo, dispatchSelf, render, mount) must
| never be redeclared as component actions.
|
*/

it('declares no component action named dispatch (it collides with Livewire::dispatch)', function () {
    // Unit tests do not bootstrap the application container, so resolve paths from
    // the project root rather than the app() helpers.
    $base = dirname(__DIR__, 3);

    $roots = [
        $base.'/resources/views/livewire',
        $base.'/app/Livewire',
    ];

    $offenders = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $finder = (new Finder)
            ->files()
            ->in($root)
            ->name(['*.blade.php', '*.php']);

        foreach ($finder as $file) {
            $contents = $file->getContents();

            // Match a public (or unqualified) `function dispatch(` declaration —
            // the reserved Livewire method. `$this->dispatch(` calls are fine.
            if (preg_match('/(?:public\s+)?function\s+dispatch\s*\(/', $contents) === 1) {
                $offenders[] = $file->getRealPath();
            }
        }
    }

    expect($offenders)->toBe(
        [],
        'These components declare a `dispatch()` method, which shadows '
        .'Livewire\Component::dispatch() and crashes the component: '
        .implode(', ', $offenders),
    );
});
