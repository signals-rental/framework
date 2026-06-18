<?php

namespace App\ValueObjects;

use App\Contracts\ShortageResolverContract;
use App\Enums\ShortageResolutionType;

/**
 * A concrete resolution proposal produced by a resolver for a shortage
 * (shortage-resolution-sub-hires.md §3.3). Each option is something the user (or
 * the auto-resolver) can accept; accepting it calls the resolver's
 * {@see ShortageResolverContract::apply()}.
 */
final readonly class ResolutionOption
{
    /**
     * @param  array<string, mixed>  $metadata  resolver-specific data (JSON-serialisable)
     */
    public function __construct(
        public string $resolverKey,
        public ShortageResolutionType $type,
        public string $label,
        public string $description,
        public int $quantityResolved,
        public bool $isPartial,
        public bool $autoExecutable,
        public ?int $estimatedCost = null,
        public ?int $estimatedLeadTimeMinutes = null,
        public bool $requiresConfirmation = true,
        public array $metadata = [],
    ) {}

    /**
     * @return array{
     *     resolver_key: string,
     *     type: string,
     *     label: string,
     *     description: string,
     *     quantity_resolved: int,
     *     is_partial: bool,
     *     auto_executable: bool,
     *     estimated_cost: int|null,
     *     estimated_lead_time: int|null,
     *     requires_confirmation: bool,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'resolver_key' => $this->resolverKey,
            'type' => $this->type->value,
            'label' => $this->label,
            'description' => $this->description,
            'quantity_resolved' => $this->quantityResolved,
            'is_partial' => $this->isPartial,
            'auto_executable' => $this->autoExecutable,
            'estimated_cost' => $this->estimatedCost,
            'estimated_lead_time' => $this->estimatedLeadTimeMinutes,
            'requires_confirmation' => $this->requiresConfirmation,
            'metadata' => $this->metadata,
        ];
    }
}
