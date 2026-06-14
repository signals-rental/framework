<?php

use App\Services\Calendar\OwnerColorResolver;

beforeEach(function () {
    $this->resolver = new OwnerColorResolver;
});

it('maps a user id to a signals avatar colour token', function () {
    expect($this->resolver->for(1))->toBe('s-avatar-blue');
});

it('is deterministic and stable across repeated calls', function () {
    $first = $this->resolver->for(42);
    $second = $this->resolver->for(42);
    $third = (new OwnerColorResolver)->for(42);

    expect($first)->toBe($second)->toBe($third);
});

it('spreads ids across the full palette in order', function () {
    $palette = [
        's-avatar-green',
        's-avatar-blue',
        's-avatar-amber',
        's-avatar-violet',
        's-avatar-cyan',
        's-avatar-navy',
    ];

    foreach ($palette as $userId => $expected) {
        expect($this->resolver->for($userId))->toBe($expected);
    }
});

it('wraps around the palette using modulo', function () {
    // 6 colours in the palette, so id 6 wraps to index 0, id 7 to index 1.
    expect($this->resolver->for(6))->toBe($this->resolver->for(0))
        ->and($this->resolver->for(7))->toBe($this->resolver->for(1))
        ->and($this->resolver->for(13))->toBe($this->resolver->for(1));
});
