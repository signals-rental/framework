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
    /** RMS-style comment / free-text line — name only, no charge or demand. */
    case Text = 'text';

    /** Group and text rows are never priced. */
    public function isPriceable(): bool
    {
        return $this !== self::Group && $this !== self::Text;
    }

    /** Only physical lines (product/accessory) claim availability. */
    public function generatesDemand(): bool
    {
        return $this === self::Product || $this === self::Accessory;
    }
}
