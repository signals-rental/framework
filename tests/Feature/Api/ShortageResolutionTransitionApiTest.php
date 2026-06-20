<?php

use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * Issue a PATCH transition request as a token holder.
 *
 * @param  array<string, mixed>  $body
 * @param  list<string>  $abilities
 * @return TestResponse<JsonResponse>
 */
function patchTransition(
    TestCase $test,
    User $owner,
    ShortageResolution $resolution,
    string $transition,
    array $body = [],
    array $abilities = ['shortages:write'],
): TestResponse {
    $token = $owner->createToken('test', $abilities)->plainTextToken;

    return $test->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/shortage_resolutions/{$resolution->id}/{$transition}", $body);
}

describe('PATCH shortage_resolutions/{id}/confirm', function () {
    it('confirms a pending resolution and stamps the confirmer', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        $response = patchTransition($this, $this->owner, $resolution, 'confirm')->assertOk();

        expect($response->json('resolution.status'))->toBe('confirmed')
            ->and($response->json('resolution.status_label'))->toBe('Confirmed');

        $resolution->refresh();
        expect($resolution->status)->toBe(ShortageResolutionStatus::Confirmed)
            ->and($resolution->confirmed_by)->toBe($this->owner->id)
            ->and($resolution->confirmed_at)->not->toBeNull();
    });

    it('confirms a monitoring (waitlist) resolution', function () {
        $resolution = ShortageResolution::factory()->monitoring()->create();

        patchTransition($this, $this->owner, $resolution, 'confirm')->assertOk();

        expect($resolution->refresh()->status)->toBe(ShortageResolutionStatus::Confirmed);
    });

    it('422s confirming an already-cancelled resolution', function () {
        $resolution = ShortageResolution::factory()->cancelled()->create();

        patchTransition($this, $this->owner, $resolution, 'confirm')->assertStatus(422)
            ->assertJsonValidationErrorFor('status');

        expect($resolution->refresh()->status)->toBe(ShortageResolutionStatus::Cancelled);
    });

    it('422s confirming an already-confirmed resolution', function () {
        $resolution = ShortageResolution::factory()->create(); // confirmed by default

        patchTransition($this, $this->owner, $resolution, 'confirm')->assertStatus(422)
            ->assertJsonValidationErrorFor('status');
    });

    it('requires the shortages:write ability', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        patchTransition($this, $this->owner, $resolution, 'confirm', [], ['shortages:read'])->assertForbidden();
    });

    it('requires authentication', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        $this->patchJson("/api/v1/shortage_resolutions/{$resolution->id}/confirm")
            ->assertUnauthorized();
    });

    it('404s for an unknown resolution', function () {
        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/v1/shortage_resolutions/999999/confirm')
            ->assertNotFound();
    });
});

describe('PATCH shortage_resolutions/{id}/start', function () {
    it('moves a confirmed resolution to in_progress', function () {
        $resolution = ShortageResolution::factory()->create(); // confirmed

        $response = patchTransition($this, $this->owner, $resolution, 'start')->assertOk();

        expect($response->json('resolution.status'))->toBe('in_progress');
        expect($resolution->refresh()->status)->toBe(ShortageResolutionStatus::InProgress);
    });

    it('422s starting a pending resolution', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        patchTransition($this, $this->owner, $resolution, 'start')->assertStatus(422);
    });
});

describe('PATCH shortage_resolutions/{id}/fulfill', function () {
    it('fulfils an in_progress resolution and stamps fulfilled_at', function () {
        $resolution = ShortageResolution::factory()->create([
            'status' => ShortageResolutionStatus::InProgress->value,
        ]);

        patchTransition($this, $this->owner, $resolution, 'fulfill')->assertOk();

        $resolution->refresh();
        expect($resolution->status)->toBe(ShortageResolutionStatus::Fulfilled)
            ->and($resolution->fulfilled_at)->not->toBeNull();
    });

    it('422s fulfilling a confirmed (not yet in-progress) resolution', function () {
        $resolution = ShortageResolution::factory()->create(); // confirmed

        patchTransition($this, $this->owner, $resolution, 'fulfill')->assertStatus(422);
    });
});

describe('PATCH shortage_resolutions/{id}/cancel', function () {
    it('cancels a pending resolution with a reason', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        $response = patchTransition($this, $this->owner, $resolution, 'cancel', ['reason' => 'No longer needed'])
            ->assertOk();

        expect($response->json('resolution.status'))->toBe('cancelled');

        $resolution->refresh();
        expect($resolution->status)->toBe(ShortageResolutionStatus::Cancelled)
            ->and($resolution->cancellation_reason)->toBe('No longer needed')
            ->and($resolution->cancelled_at)->not->toBeNull();
    });

    it('cancels a confirmed resolution', function () {
        $resolution = ShortageResolution::factory()->create();

        patchTransition($this, $this->owner, $resolution, 'cancel')->assertOk();

        expect($resolution->refresh()->status)->toBe(ShortageResolutionStatus::Cancelled);
    });

    it('422s cancelling an in_progress resolution (not allowed by §8.3)', function () {
        $resolution = ShortageResolution::factory()->create([
            'status' => ShortageResolutionStatus::InProgress->value,
        ]);

        patchTransition($this, $this->owner, $resolution, 'cancel')->assertStatus(422);
    });
});

describe('PATCH shortage_resolutions/{id}/fail', function () {
    it('fails a pending resolution with a reason', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        patchTransition($this, $this->owner, $resolution, 'fail', ['reason' => 'Supplier declined'])->assertOk();

        $resolution->refresh();
        expect($resolution->status)->toBe(ShortageResolutionStatus::Failed)
            ->and($resolution->cancellation_reason)->toBe('Supplier declined');
    });

    it('422s failing a confirmed resolution (not allowed by §8.3)', function () {
        $resolution = ShortageResolution::factory()->create();

        patchTransition($this, $this->owner, $resolution, 'fail')->assertStatus(422);
    });
});
