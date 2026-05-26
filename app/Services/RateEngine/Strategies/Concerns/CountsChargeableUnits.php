<?php

namespace App\Services\RateEngine\Strategies\Concerns;

use App\Enums\BasePeriod;
use App\Enums\DayType;
use App\Support\ConfigSchema\Field;
use App\Support\ConfigSchema\Fields\GroupField;
use App\Support\ConfigSchema\Fields\NumberField;
use App\Support\ConfigSchema\Fields\SelectField;
use App\Support\ConfigSchema\Fields\TimeField;
use App\ValueObjects\CalculationContext;
use App\ValueObjects\RentalPeriod;

/**
 * Shared helpers for strategies that translate a rental window into a count of
 * chargeable units for a base period, and that label those units for display.
 */
trait CountsChargeableUnits
{
    /**
     * The shared time-interpretation config fields (day type, business hours,
     * rental week length, leeway, cut-offs) used by every duration-aware
     * strategy. {@see RentalPeriod::chargeableUnits()} consumes these.
     *
     * @return array<int, Field>
     */
    protected function timeOptionFields(): array
    {
        return [
            SelectField::make('day_type')->label('Day Type')
                ->options(['clock' => 'Clock', 'business' => 'Business Hours'])
                ->default('clock'),
            GroupField::make('business_hours')->label('Business Hours')
                ->fields(
                    TimeField::make('business_hours_start')->label('Opens')->required()->default('09:00'),
                    TimeField::make('business_hours_end')->label('Closes')->required()->default('17:00'),
                )
                ->visibleWhen('day_type', 'business'),
            NumberField::make('rental_days_per_week')->label('Rental Days per Week')->default(7)->min(1)->max(7),
            NumberField::make('leeway_minutes')->label('Leeway Minutes')->default(0)->min(0),
            TimeField::make('first_day_cutoff')->label('First Day Cut-off'),
            TimeField::make('last_day_cutoff')->label('Last Day Cut-off'),
        ];
    }

    /**
     * Count the chargeable units in the context's window for the given base
     * period, applying the time options carried in the strategy config.
     */
    protected function chargeableUnits(CalculationContext $context, BasePeriod $period): int
    {
        return (new RentalPeriod($context->start, $context->end))
            ->chargeableUnits($period, $this->timeOptions($context));
    }

    /**
     * Coerce the raw strategy config into the time-option shape
     * {@see RentalPeriod::chargeableUnits()} expects, mapping the `day_type`
     * string onto the {@see DayType} enum.
     *
     * @return array<string, mixed>
     */
    protected function timeOptions(CalculationContext $context): array
    {
        $options = $context->strategyConfig;

        if (isset($options['day_type']) && is_string($options['day_type'])) {
            $options['day_type'] = DayType::tryFrom($options['day_type']) ?? DayType::Clock;
        }

        return $options;
    }

    /**
     * Pluralised, human-readable label for a single base-period unit.
     */
    protected function unitLabel(BasePeriod $period): string
    {
        return match ($period) {
            BasePeriod::HalfHourly => 'half-hours',
            BasePeriod::Hourly => 'hours',
            BasePeriod::Daily => 'days',
            BasePeriod::Weekly => 'weeks',
            BasePeriod::Monthly => 'months',
        };
    }
}
