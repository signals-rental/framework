<?php

namespace App\Enums;

/**
 * Row type in the Current-RMS-style flat line-item grid used by the throwaway
 * "Editor Lab" prototypes. Every prototype row is one of these; nesting and
 * order live in the materialized `path`, not in the type.
 */
enum PrototypeItemType: string
{
    case Group = 'group';
    case Product = 'product';
    case Accessory = 'accessory';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Group',
            self::Product => 'Product',
            self::Accessory => 'Accessory',
            self::Service => 'Service',
        };
    }
}
