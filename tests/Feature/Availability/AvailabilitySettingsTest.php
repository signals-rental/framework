<?php

use App\Contracts\Availability\AvailabilityDataPresence;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Models\User;
use App\Services\Availability\SettingsAvailabilityResolutionProvider;
use App\Services\SettingsRegistry;
use App\Services\SettingsService;
use App\Settings\AvailabilitySettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

/**
 * Fake presence implementation so the immutability guard can be driven both ways
 * without needing a real `demands` table (which does not exist in M1).
 */
function fakeAvailabilityPresence(bool $exists): void
{
    app()->bind(AvailabilityDataPresence::class, fn (): AvailabilityDataPresence => new class($exists) implements AvailabilityDataPresence
    {
        public function __construct(private bool $exists) {}

        public function exists(): bool
        {
            return $this->exists;
        }
    });
}

describe('AvailabilityResolution enum', function () {
    it('is string-backed with the expected cases', function () {
        expect(AvailabilityResolution::Hourly->value)->toBe('hourly')
            ->and(AvailabilityResolution::HalfDaily->value)->toBe('half_daily')
            ->and(AvailabilityResolution::Daily->value)->toBe('daily');
    });

    it('exposes human labels', function () {
        expect(AvailabilityResolution::Hourly->label())->toBe('Hourly')
            ->and(AvailabilityResolution::HalfDaily->label())->toBe('Half-daily')
            ->and(AvailabilityResolution::Daily->label())->toBe('Daily');
    });
});

describe('availability settings definition', function () {
    it('defaults the resolution to daily', function () {
        expect(settings('availability.resolution'))->toBe('daily');
    });

    it('is registered in the settings registry', function () {
        $registry = app(SettingsRegistry::class);

        expect($registry->has('availability'))->toBeTrue()
            ->and($registry->get('availability'))->toBeInstanceOf(AvailabilitySettings::class);
    });

    it('round-trips a set then read of a valid value', function () {
        settings()->set('availability.resolution', AvailabilityResolution::Hourly->value);

        expect(settings('availability.resolution'))->toBe('hourly');
    });
});

describe('availability resolution provider', function () {
    it('binds the settings-backed default implementation', function () {
        expect(app(AvailabilityResolutionProvider::class))
            ->toBeInstanceOf(SettingsAvailabilityResolutionProvider::class);
    });

    it('resolves the enum from the stored setting', function () {
        settings()->set('availability.resolution', AvailabilityResolution::Hourly->value);

        expect(app(AvailabilityResolutionProvider::class)->resolve())
            ->toBe(AvailabilityResolution::Hourly);
    });

    it('falls back to daily when the setting is unset', function () {
        expect(app(AvailabilityResolutionProvider::class)->resolve())
            ->toBe(AvailabilityResolution::Daily);
    });
});

describe('availability data presence (default impl)', function () {
    it('reports no availability data in M1 (demands table absent)', function () {
        expect(app(AvailabilityDataPresence::class)->exists())->toBeFalse();
    });
});

describe('immutability guard (unit)', function () {
    it('allows a change when no availability data exists', function () {
        fakeAvailabilityPresence(false);

        $definition = new AvailabilitySettings;

        $definition->guard(['resolution' => 'hourly'], app(SettingsService::class));
    })->throwsNoExceptions();

    it('rejects a change when availability data exists', function () {
        fakeAvailabilityPresence(true);

        $definition = new AvailabilitySettings;

        expect(fn () => $definition->guard(['resolution' => 'hourly'], app(SettingsService::class)))
            ->toThrow(ValidationException::class);
    });

    it('allows setting the same value while data exists (no-op change)', function () {
        fakeAvailabilityPresence(true);
        settings()->set('availability.resolution', AvailabilityResolution::Daily->value);

        $definition = new AvailabilitySettings;

        $definition->guard(['resolution' => 'daily'], app(SettingsService::class));
    })->throwsNoExceptions();

    it('is a no-op when the resolution key is absent from the input', function () {
        fakeAvailabilityPresence(true);

        $definition = new AvailabilitySettings;

        $definition->guard([], app(SettingsService::class));
    })->throwsNoExceptions();
});

describe('immutability guard via the settings API', function () {
    beforeEach(function () {
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->user = User::factory()->owner()->create();
    });

    it('allows changing the resolution when no availability data exists', function () {
        fakeAvailabilityPresence(false);
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/availability', [
                'settings' => ['resolution' => 'hourly'],
            ])
            ->assertOk()
            ->assertJsonPath('setting.settings.resolution', 'hourly');

        expect(settings('availability.resolution'))->toBe('hourly');
    });

    it('rejects changing the resolution as 422 when availability data exists', function () {
        fakeAvailabilityPresence(true);
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/availability', [
                'settings' => ['resolution' => 'hourly'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resolution']);

        expect(settings('availability.resolution'))->toBe('daily');
    });

    it('rejects an invalid resolution value via validation', function () {
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/availability', [
                'settings' => ['resolution' => 'weekly'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resolution']);
    });

    it('does not apply the availability guard to other settings groups', function () {
        fakeAvailabilityPresence(true);
        $token = $this->user->createToken('test', ['settings:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/security', [
                'settings' => ['password_min_length' => 14],
            ])
            ->assertOk()
            ->assertJsonPath('setting.settings.password_min_length', 14);
    });
});
