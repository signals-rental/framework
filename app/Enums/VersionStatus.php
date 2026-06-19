<?php

namespace App\Enums;

/**
 * Workflow status of a single quote version (opportunity-lifecycle.md §8.6).
 *
 * A version is created as a Draft. It is Sent to the customer, then either
 * Accepted or Declined. When a newer revision is created (or the opportunity is
 * confirmed against a different version) the others are Superseded.
 *
 * Values are RMS-aligned integers persisted on `opportunity_versions.status`.
 */
enum VersionStatus: int
{
    case Draft = 0;

    case Sent = 1;

    case Accepted = 2;

    case Declined = 3;

    case Superseded = 4;

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Superseded => 'Superseded',
        };
    }
}
