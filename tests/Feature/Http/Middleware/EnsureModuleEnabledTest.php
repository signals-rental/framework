<?php

use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(EnsureModuleEnabled::class.':crm')
        ->get('/test-module', fn () => 'ok')
        ->name('test.module');
});

it('allows access when module is enabled', function () {
    settings()->set('modules.crm', true, 'boolean');

    $this->get('/test-module')->assertOk()->assertSee('ok');
});

it('returns 404 when module is disabled', function () {
    settings()->set('modules.crm', false, 'boolean');

    $this->get('/test-module')->assertNotFound();
});

it('returns 404 when module setting does not exist', function () {
    $this->get('/test-module')->assertNotFound();
});
