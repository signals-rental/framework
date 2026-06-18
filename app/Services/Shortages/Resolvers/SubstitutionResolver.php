<?php

namespace App\Services\Shortages\Resolvers;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;
use Illuminate\Support\Facades\Schema;

/**
 * Product substitution (shortage-resolution-sub-hires.md §4.2).
 *
 * Offers an alternative product that can replace the short one. This depends on
 * the `product_substitutions` table, which is not part of this milestone's scope.
 * The resolver therefore stays dormant (offers no options) until that table
 * exists; when invoked directly it records the substitution INTENT as pending and
 * notes the dependency. This keeps the resolver registered and discoverable while
 * its data source is built.
 */
class SubstitutionResolver extends AbstractShortageResolver
{
    public function key(): string
    {
        return 'substitute';
    }

    public function name(): string
    {
        return 'Substitute product';
    }

    public function priority(): int
    {
        return 20;
    }

    public function isAutoExecutable(): bool
    {
        // TODO(M7): make configurable per relationship/warehouse-pair
        return false;
    }

    /**
     * Only applicable once the substitutions data source exists.
     */
    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0 && Schema::hasTable('product_substitutions');
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        // Without the product_substitutions table there are no candidates to
        // offer. Populated once the substitutions domain is built.
        return [];
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $shortage->remainingShortfall(),
            status: ShortageResolutionStatus::Pending,
            metadata: [
                'pending_dependency' => 'product_substitutions',
                'substitute_product_id' => $option->metadata['substitute_product_id'] ?? null,
            ],
        );

        return ResolutionResult::pending(
            $resolution,
            'Recorded substitution intent; the product-substitutions domain is not yet available.',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Substitute;
    }
}
