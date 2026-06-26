<?php

namespace App\ValueObjects;

use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;

/**
 * The outcome of executing a resolution option
 * (shortage-resolution-sub-hires.md §3.4). Wraps the persisted resolution record
 * (when one was created) and the human-readable outcome.
 */
final readonly class ResolutionResult
{
    /**
     * `$success` means the resolve OPERATION succeeded in recording an outcome —
     * NOT that the shortage is fully covered. A `pending()` or `monitoring()` result
     * is therefore `success: true` (the intent was recorded) AND `requiresFollowup:
     * true` (an external/user action or unbuilt domain must finish it). Consumers
     * that need "is the shortage actually cleared?" must read `status` /
     * `requiresFollowup`, not `success`.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $success,
        public ShortageResolutionStatus $status,
        public string $message,
        public ?ShortageResolution $resolution = null,
        public bool $requiresFollowup = false,
        public ?string $followupType = null,
        public array $metadata = [],
    ) {}

    /**
     * A confirmed (immediately effective) result wrapping its resolution record.
     */
    public static function confirmed(ShortageResolution $resolution, string $message): self
    {
        return new self(
            success: true,
            status: ShortageResolutionStatus::Confirmed,
            message: $message,
            resolution: $resolution,
        );
    }

    /**
     * A pending result: the resolution intent was recorded but awaits an unbuilt
     * domain or an external/user action (e.g. transfer creation, quote release).
     */
    public static function pending(ShortageResolution $resolution, string $message, ?string $followupType = null): self
    {
        return new self(
            success: true,
            status: ShortageResolutionStatus::Pending,
            message: $message,
            resolution: $resolution,
            requiresFollowup: true,
            followupType: $followupType,
        );
    }

    /**
     * A monitoring result: the waitlist state — watching for availability.
     */
    public static function monitoring(ShortageResolution $resolution, string $message): self
    {
        return new self(
            success: true,
            status: ShortageResolutionStatus::Monitoring,
            message: $message,
            resolution: $resolution,
            requiresFollowup: true,
            followupType: 'availability',
        );
    }
}
