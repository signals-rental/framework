<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Resolve the tenant from the request subdomain and switch the database connection.
     *
     * On Signals Cloud, each tenant has its own PostgreSQL database named
     * tenant_{slug}. This middleware extracts the slug from the subdomain,
     * reconfigures the pgsql connection, and eagerly reconnects so that
     * all downstream code uses the correct database.
     *
     * Self-hosted installations (SIGNALS_CLOUD=false) skip this entirely.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('signals.cloud')) {
            return $next($request);
        }

        $host = $request->getHost();
        $baseDomain = config('signals.cloud_domain', 'signals.cloud');
        $suffix = '.'.$baseDomain;

        if ($host === $baseDomain || ! str_ends_with($host, $suffix)) {
            return $this->noTenantResponse($baseDomain);
        }

        $slug = substr($host, 0, -strlen($suffix));

        if ($slug === '' || ! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $slug)) {
            return $this->noTenantResponse($baseDomain);
        }

        config([
            'database.connections.pgsql.database' => 'tenant_'.$slug,
            'signals.tenant' => $slug,
        ]);

        DB::purge('pgsql');

        try {
            DB::connection('pgsql')->getPdo();
        } catch (\Throwable) {
            return $this->tenantUnavailableResponse($slug);
        }

        return $next($request);
    }

    private function noTenantResponse(string $baseDomain): Response
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>No Tenant Specified</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: ui-sans-serif, system-ui, sans-serif; background: #0a0f1e; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .container { text-align: center; max-width: 28rem; padding: 2rem; }
                h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.75rem; }
                p { color: #94a3b8; line-height: 1.6; }
                code { background: #1e293b; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.875rem; color: #38bdf8; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>No Tenant Specified</h1>
                <p>Please access Signals Cloud via your workspace subdomain, e.g. <code>your-company.{$baseDomain}</code></p>
            </div>
        </body>
        </html>
        HTML;

        return new Response($html, 404);
    }

    private function tenantUnavailableResponse(string $slug): Response
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Tenant Unavailable</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: ui-sans-serif, system-ui, sans-serif; background: #0a0f1e; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .container { text-align: center; max-width: 28rem; padding: 2rem; }
                h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: 0.75rem; }
                p { color: #94a3b8; line-height: 1.6; }
                code { background: #1e293b; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.875rem; color: #38bdf8; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Tenant Unavailable</h1>
                <p>The workspace <code>{$slug}</code> could not be reached. The database <code>tenant_{$slug}</code> does not exist or is not available.</p>
            </div>
        </body>
        </html>
        HTML;

        return new Response($html, 503);
    }
}
