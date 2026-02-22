<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupRequired
{
    /**
     * Ensure the application is installed but setup is not yet complete.
     * Gates the /setup routes — only accessible during initial setup.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('signals.installed')) {
            abort(404);
        }

        if (config('signals.setup_complete')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
