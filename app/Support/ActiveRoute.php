<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the *stable* active route name for the current page.
 *
 * Navigation active states must not be derived from `request()->routeIs()`
 * because, during a Livewire update (`POST /livewire/update`), `request()`
 * points at the Livewire endpoint rather than the page the user is viewing.
 * Any morph re-render of a component that contains navigation would then
 * recompute the active state against the wrong route and lose/break it.
 *
 * Livewire persists the original page path in the component snapshot, exposed
 * via {@see Livewire::originalUrl()}. Resolving the route name from that URL
 * yields a value that is identical on the initial page load and on every
 * subsequent Livewire update, making navigation highlighting request-independent.
 */
class ActiveRoute
{
    /**
     * Resolve the route name of the page currently being viewed.
     *
     * Returns null when the URL cannot be matched to a named route.
     */
    public static function name(): ?string
    {
        $url = Livewire::originalUrl();

        try {
            $route = Route::getRoutes()->match(Request::create($url));
        } catch (HttpException|UrlGenerationException) {
            return null;
        }

        return $route->getName();
    }

    /**
     * Determine whether the current page matches any of the given route-name
     * patterns. Patterns support `*` wildcards, mirroring `Route::is()`.
     */
    public static function is(string ...$patterns): bool
    {
        $name = self::name();

        if ($name === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
