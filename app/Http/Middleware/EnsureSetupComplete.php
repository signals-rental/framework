<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Redirect to /setup when setup has not been completed.
     * Applied to authenticated routes that require a fully set-up application.
     *
     * Checks config first (fast), then falls back to the settings table
     * in case the .env write failed during setup completion.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('signals.installed')) {
            return redirect()->route('home');
        }

        if (! config('signals.setup_complete') && ! $this->isSetupCompleteInDatabase()) {
            return redirect()->route('setup.wizard');
        }

        return $next($request);
    }

    /**
     * Check the settings table for setup.completed_at as a fallback.
     * If found, self-heal the config and .env so the fast path works next time.
     */
    private function isSetupCompleteInDatabase(): bool
    {
        try {
            if (settings()->has('setup.completed_at')) {
                config(['signals.setup_complete' => true]);

                Env::writeVariables(
                    ['SIGNALS_SETUP_COMPLETE' => 'true'],
                    app()->basePath('.env'),
                    overwrite: true,
                );

                return true;
            }
        } catch (\Throwable) {
            // Database may not be available yet — fall through to redirect.
        }

        return false;
    }
}
