<?php

namespace App\Settings;

use App\Contracts\Availability\AvailabilityDataPresence;
use App\Enums\AvailabilityResolution;
use App\Services\SettingsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvailabilitySettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'availability';
    }

    public function defaults(): array
    {
        return [
            'resolution' => AvailabilityResolution::Daily->value,
        ];
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::enum(AvailabilityResolution::class)],
        ];
    }

    public function types(): array
    {
        return [
            'resolution' => 'string',
        ];
    }

    /**
     * Enforce that the availability resolution is immutable once availability
     * data exists.
     *
     * Changing the resolution after demands or snapshots exist would require
     * re-bucketing the entire dataset to the new granularity, which is not
     * supported in-place. The change is allowed when the resolution key is
     * absent, when it matches the currently stored value (a no-op), or when no
     * availability data exists yet. Otherwise it is rejected as a 422.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function guard(array $input, SettingsService $settings): void
    {
        if (! array_key_exists('resolution', $input)) {
            return;
        }

        $current = $settings->get('availability.resolution', AvailabilityResolution::Daily->value);

        if ($input['resolution'] === $current) {
            return;
        }

        if (! app(AvailabilityDataPresence::class)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'resolution' => __('The availability resolution cannot be changed once availability data exists.'),
        ]);
    }
}
