<?php

use App\Support\ActiveRoute;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Point Livewire::originalUrl() at a fixed URL so ActiveRoute resolves the route
 * name from a deterministic page URL rather than the live request.
 */
function fakeOriginalUrl(?string $url): void
{
    Livewire::shouldReceive('originalUrl')->andReturn($url);
}

it('resolves the route name for a matched URL', function () {
    fakeOriginalUrl(url('/dashboard'));

    expect(ActiveRoute::name())->toBe('dashboard');
});

it('returns null for an unmatched URL (HttpException branch)', function () {
    fakeOriginalUrl(url('/this-route-definitely-does-not-exist-'.uniqid()));

    expect(ActiveRoute::name())->toBeNull();
});

it('matches an exact route-name pattern', function () {
    fakeOriginalUrl(url('/dashboard'));

    expect(ActiveRoute::is('dashboard'))->toBeTrue();
});

it('matches a wildcard route-name pattern', function () {
    fakeOriginalUrl(url('/dashboard'));

    expect(ActiveRoute::is('dash*'))->toBeTrue();
});

it('does not match a non-matching pattern', function () {
    fakeOriginalUrl(url('/dashboard'));

    expect(ActiveRoute::is('members.*'))->toBeFalse();
});

it('returns false from is() when the URL cannot be matched', function () {
    fakeOriginalUrl(url('/no-such-route-'.uniqid()));

    expect(ActiveRoute::is('*'))->toBeFalse();
});
