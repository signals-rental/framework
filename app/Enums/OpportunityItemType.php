<?php

namespace App\Enums;

/**
 * Structural role of an opportunity line-item row in the unified, Current-RMS
 * line-item model. Distinct from the polymorphic catalogue reference
 * (`itemable_type`/`itemable_id`).
 */
enum OpportunityItemType: string
{
    case Group = 'group';
    case Product = 'product';
    case Accessory = 'accessory';
    case Service = 'service';
    /** Free-form text line — user-entered name and manual price, no catalogue item. */
    case Text = 'text';

    /** Only group rows are structural containers without a charge. */
    public function isPriceable(): bool
    {
        return $this !== self::Group;
    }

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Group',
            self::Product => 'Product',
            self::Accessory => 'Accessory',
            self::Service => 'Service',
            self::Text => 'Free text item',
        };
    }

    /** Only physical lines (product/accessory) claim availability. */
    public function generatesDemand(): bool
    {
        return $this === self::Product || $this === self::Accessory;
    }
}
