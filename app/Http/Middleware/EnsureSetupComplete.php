<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Redirect to /setup when setup has not been completed.
     * Applied to authenticated routes that require a fully set-up application.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('signals.installed')) {
            return redirect()->route('home');
        }

        if (! config('signals.setup_complete')) {
            return redirect()->route('setup.wizard');
        }

        return $next($request);
    }
}
