<?php

namespace App\Contracts;

use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;

/**
 * Contract every shortage resolver implements
 * (shortage-resolution-sub-hires.md §3.2).
 *
 * Resolvers are registered in the {@see ShortageResolverRegistry} and resolved
 * from the container, so they are mockable in tests and replaceable/extendable by
 * plugins. The shortage panel collects options from every applicable resolver
 * without special-casing any of them.
 *
 * The virtual-stock / sub-hire `execute → confirm → intake → assets` flow from
 * the spec is out of scope for this milestone (Phase 4); the contract here covers
 * the non-PO resolvers, which {@see apply()} a chosen option by recording a
 * resolution (and items) and performing any self-contained side effect.
 */
interface ShortageResolverContract
{
    /**
     * Unique identifier for this resolver, used in configuration, resolution
     * records, and event payloads (e.g. `reallocate`, `substitute`, `transfer`,
     * `date_shift`, `partial`, `waitlist`).
     */
    public function key(): string;

    /**
     * Human-readable name for display in the UI.
     */
    public function name(): string;

    /**
     * Display ordering — lower numbers appear first. Built-in resolvers use
     * 10–90; plugins should use 25+ to interleave.
     */
    public function priority(): int;

    /**
     * Whether this resolver supports automatic execution without user
     * confirmation. Store settings control which auto-executable resolvers are
     * actually enabled for auto-resolution.
     */
    public function isAutoExecutable(): bool;

    /**
     * Whether this resolver is applicable to a specific shortage. Returns false
     * to skip (e.g. the transfer resolver returns false in a single-warehouse
     * store).
     */
    public function canResolve(Shortage $shortage): bool;

    /**
     * Generate zero or more concrete resolution options for a shortage.
     *
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array;

    /**
     * Execute a chosen option: create the resolution record (+ items), perform any
     * self-contained side effect, and return the outcome. Resolvers that depend on
     * an unbuilt domain record the resolution intent as pending and note the
     * dependency rather than performing the side effect.
     */
    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult;
}
