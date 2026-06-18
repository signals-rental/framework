<?php

namespace App\Actions\Shortages;

use App\Data\Shortages\ApplyResolutionData;
use App\Models\OpportunityItem;
use App\Services\Shortages\ShortageDetector;
use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\ResolutionResult;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies a chosen resolver option to the current shortage on a line item
 * (shortage-resolution-sub-hires.md §3.2 `execute`).
 *
 * The shortage is recomputed fresh (it is transient), the named resolver is
 * resolved from the registry, its option at the requested index is selected, and
 * its `apply()` records the resolution. Guards reject applying a resolver that is
 * not applicable to the shortage, or selecting an out-of-range option.
 */
class ApplyShortageResolution
{
    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageResolverRegistry $registry,
    ) {}

    public function __invoke(ApplyResolutionData $data): ResolutionResult
    {
        Gate::authorize('shortages.resolve');

        $item = OpportunityItem::query()->findOrFail($data->opportunity_item_id);

        $shortage = $this->detector->forItem($item);

        if ($shortage === null) {
            throw ValidationException::withMessages([
                'opportunity_item_id' => ['This line item has no current shortage to resolve.'],
            ]);
        }

        if (! $this->registry->has($data->resolver_key)) {
            throw ValidationException::withMessages([
                'resolver_key' => ["Unknown shortage resolver: {$data->resolver_key}."],
            ]);
        }

        $resolver = $this->registry->resolve($data->resolver_key);

        if (! $resolver->canResolve($shortage)) {
            throw ValidationException::withMessages([
                'resolver_key' => ["The {$resolver->name()} resolver is not applicable to this shortage."],
            ]);
        }

        $options = $resolver->getOptions($shortage);

        if (! array_key_exists($data->option_index, $options)) {
            throw ValidationException::withMessages([
                'option_index' => ['The selected resolution option is no longer available.'],
            ]);
        }

        return $resolver->apply($shortage, $options[$data->option_index]);
    }
}
