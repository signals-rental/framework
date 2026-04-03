<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->originalDatabase = config('database.connections.pgsql.database');
    config(['signals.cloud_domain' => 'signals.cloud']);
});

afterEach(function () {
    config([
        'database.connections.pgsql.database' => $this->originalDatabase,
        'signals.tenant' => null,
    ]);
});

it('is a no-op when signals cloud is disabled', function () {
    config(['signals.cloud' => false]);

    $request = Request::create('https://acme.signals.cloud/dashboard');

    $middleware = new ResolveTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
    expect(config('database.connections.pgsql.database'))->toBe($this->originalDatabase);
});

it('sets the database name from the subdomain when cloud is enabled', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://acme.signals.cloud/dashboard');

    $connection = Mockery::mock();
    $connection->shouldReceive('getPdo')->once();

    DB::shouldReceive('purge')->once()->with('pgsql');
    DB::shouldReceive('connection')->once()->with('pgsql')->andReturn($connection);

    $middleware = new ResolveTenant;
    $middleware->handle($request, fn () => response('ok'));

    expect(config('database.connections.pgsql.database'))->toBe('tenant_acme');
    expect(config('signals.tenant'))->toBe('acme');
});

it('returns no-tenant page when the host is the bare domain on cloud', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://signals.cloud/dashboard');

    $middleware = new ResolveTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No Tenant Specified');
    expect($response->getContent())->toContain('your-company.signals.cloud');
});

it('returns no-tenant page when the host does not match the cloud domain', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://example.com/dashboard');

    $middleware = new ResolveTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No Tenant Specified');
});

it('rejects an empty subdomain as a malformed host', function () {
    config(['signals.cloud' => true]);

    // Symfony rejects ".signals.cloud" as a malformed host before the middleware runs
    $request = Request::create('https://.signals.cloud/dashboard');

    $middleware = new ResolveTenant;
    $middleware->handle($request, fn () => response('ok'));
})->throws(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);

it('returns no-tenant page for a subdomain with invalid characters', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://bad_slug.signals.cloud/dashboard');

    $middleware = new ResolveTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No Tenant Specified');
});

it('handles subdomains with hyphens', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://my-company.signals.cloud/dashboard');

    $connection = Mockery::mock();
    $connection->shouldReceive('getPdo')->once();

    DB::shouldReceive('purge')->once()->with('pgsql');
    DB::shouldReceive('connection')->once()->with('pgsql')->andReturn($connection);

    $middleware = new ResolveTenant;
    $middleware->handle($request, fn () => response('ok'));

    expect(config('database.connections.pgsql.database'))->toBe('tenant_my-company');
});

it('handles numeric subdomains', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://123.signals.cloud/dashboard');

    $connection = Mockery::mock();
    $connection->shouldReceive('getPdo')->once();

    DB::shouldReceive('purge')->once()->with('pgsql');
    DB::shouldReceive('connection')->once()->with('pgsql')->andReturn($connection);

    $middleware = new ResolveTenant;
    $middleware->handle($request, fn () => response('ok'));

    expect(config('database.connections.pgsql.database'))->toBe('tenant_123');
});

it('returns 503 with tenant info when the database does not exist', function () {
    config(['signals.cloud' => true]);

    $request = Request::create('https://nonexistent.signals.cloud/dashboard');

    $connection = Mockery::mock();
    $connection->shouldReceive('getPdo')->once()->andThrow(new \RuntimeException('database does not exist'));

    DB::shouldReceive('purge')->once()->with('pgsql');
    DB::shouldReceive('connection')->once()->with('pgsql')->andReturn($connection);

    $middleware = new ResolveTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(503);
    expect($response->getContent())->toContain('nonexistent');
    expect($response->getContent())->toContain('tenant_nonexistent');
    expect($response->getContent())->toContain('Tenant Unavailable');
});
