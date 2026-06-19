<?php

namespace App\Settings;

use App\Contracts\Availability\AvailabilityDataPresence;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandDateSource;
use App\Enums\ReleasePoint;
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
            // Installation-level (immutable once data exists — see guard()).
            'resolution' => AvailabilityResolution::Daily->value,

            // Rolling snapshot horizon (the window the pipeline materialises and
            // the prune job retains future-side). Existing keys; canonical.
            'snapshot_horizon_past_days' => 90,
            'snapshot_horizon_future_days' => 365,

            // Demand window / release behaviour.
            'demand_date_source' => DemandDateSource::Operational->value,
            'release_point' => ReleasePoint::Returned->value,
            'default_turnaround_hours' => 4,
            'overdue_check_interval' => 60,

            // Shortage handling (read by the shortage subsystem — Track C).
            'shortage_acknowledgement_required' => true,
            'shortage_notification_roles' => ['coordinator', 'manager'],
            'overbooking_approval_required' => false,
            'shortage_warnings_at_quote' => true,

            // Retention (read by the PruneAvailabilityData job).
            'daily_summary_retention_years' => 3,
            'event_log_retention_months' => 12,

            // Operational tuning.
            'async_threshold_products' => 10,
            'kit_nesting_max_depth' => 3,
            'recalculation_lock_timeout_ms' => 5000,
        ];
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::enum(AvailabilityResolution::class)],
            'snapshot_horizon_past_days' => ['required', 'integer', 'min:0'],
            'snapshot_horizon_future_days' => ['required', 'integer', 'min:0'],
            'demand_date_source' => ['required', 'string', Rule::enum(DemandDateSource::class)],
            'release_point' => ['required', 'string', Rule::enum(ReleasePoint::class)],
            'default_turnaround_hours' => ['required', 'integer', 'min:0', 'max:8760'],
            'overdue_check_interval' => ['required', 'integer', 'min:1', 'max:1440'],
            'shortage_acknowledgement_required' => ['required', 'boolean'],
            'shortage_notification_roles' => ['array'],
            'shortage_notification_roles.*' => ['string'],
            'overbooking_approval_required' => ['required', 'boolean'],
            'shortage_warnings_at_quote' => ['required', 'boolean'],
            'daily_summary_retention_years' => ['required', 'integer', 'min:1', 'max:50'],
            'event_log_retention_months' => ['required', 'integer', 'min:1', 'max:600'],
            'async_threshold_products' => ['required', 'integer', 'min:1', 'max:10000'],
            'kit_nesting_max_depth' => ['required', 'integer', 'min:1', 'max:20'],
            'recalculation_lock_timeout_ms' => ['required', 'integer', 'min:0', 'max:60000'],
        ];
    }

    public function types(): array
    {
        return [
            'resolution' => 'string',
            'snapshot_horizon_past_days' => 'integer',
            'snapshot_horizon_future_days' => 'integer',
            'demand_date_source' => 'string',
            'release_point' => 'string',
            'default_turnaround_hours' => 'integer',
            'overdue_check_interval' => 'integer',
            'shortage_acknowledgement_required' => 'boolean',
            'shortage_notification_roles' => 'json',
            'overbooking_approval_required' => 'boolean',
            'shortage_warnings_at_quote' => 'boolean',
            'daily_summary_retention_years' => 'integer',
            'event_log_retention_months' => 'integer',
            'async_threshold_products' => 'integer',
            'kit_nesting_max_depth' => 'integer',
            'recalculation_lock_timeout_ms' => 'integer',
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
