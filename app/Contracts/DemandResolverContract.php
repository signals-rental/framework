<?php

namespace App\Contracts;

use App\Enums\DemandPhase;
use App\Services\DemandSourceRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract every demand source implements to translate its own entities into
 * availability-engine demand rows.
 *
 * Resolvers are resolved from the container via the {@see DemandSourceRegistry}
 * so they are mockable in tests and replaceable by plugins.
 */
interface DemandResolverContract
{
    /**
     * The source type identifier this resolver handles (e.g. `opportunity_item`).
     */
    public function sourceType(): string;

    /**
     * Create or update the demand row(s) for the given source entity.
     *
     * Must be idempotent: calling it repeatedly for the same source converges
     * on the same demand set rather than duplicating rows.
     */
    public function syncDemands(Model $source): void;

    /**
     * Release all demands for the given source entity (void/close them).
     */
    public function releaseDemands(Model $source): void;

    /**
     * Map the source entity's current state to a {@see DemandPhase}.
     */
    public function resolvePhase(Model $source): DemandPhase;

    /**
     * Build the source-specific metadata array stored on the demand.
     *
     * @return array<string, mixed>
     */
    public function buildMetadata(Model $source): array;
}
