<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
});

it('forces JSON response even when Accept header is not set', function () {
    $user = User::factory()->owner()->create();
    $token = $user->createToken('test', ['system:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->get('/api/v1/system/health')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json');
});

it('returns JSON for validation errors without Accept header', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('webhooks.manage');
    $token = $user->createToken('test', ['webhooks:manage'])->plainTextToken;

    // POST without Accept: application/json — middleware should force JSON response
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/webhooks', []);

    expect($response->headers->get('Content-Type'))->toContain('application/json');
    $response->assertUnprocessable();
});
