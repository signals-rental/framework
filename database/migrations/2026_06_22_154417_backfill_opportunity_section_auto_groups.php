<?php

use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use App\Services\Opportunities\OpportunityAutoGroupResolver;
use Illuminate\Database\Migrations\Migration;

/**
 * Eager group-unification backfill: turn every render-time auto group into a real,
 * persisted `opportunity_sections` row and assign its lines to it.
 *
 * For every opportunity that still has line items with `section_id IS NULL`, this
 * reproduces the historical render-time bucketing (via the SAME
 * {@see OpportunityAutoGroupResolver} the editor now uses) — keying by the product's
 * parent-group · product-group (else "Ungrouped", else "Other") — and:
 *  - find-or-creates ONE section per distinct bucket key
 *    (`auto_group_key` = the bucket key, `parent_id` = null, `sort_order` sequential
 *    AFTER any existing sections on that opportunity), then
 *  - sets those items' `section_id` to the matching section.
 *
 * After this runs, no line relies on the null-section render path. The whole thing
 * is idempotent: re-running it find-or-creates the same sections and re-assigns the
 * same items, and items already in a section are left untouched.
 *
 * Sections are plain (non-event-sourced) rows, so this projection-level backfill
 * never touches the Verbs stream; items are written with `saveQuietly()` for the
 * same reason (section_id is decoupled from the event stream).
 */
return new class extends Migration
{
    public function up(): void
    {
        $resolver = app(OpportunityAutoGroupResolver::class);

        // Opportunity ids that still carry at least one null-section line.
        $opportunityIds = OpportunityItem::query()
            ->whereNull('section_id')
            ->distinct()
            ->pluck('opportunity_id');

        foreach ($opportunityIds as $opportunityId) {
            $opportunityId = (int) $opportunityId;

            // Pre-load the products referenced by this opportunity's null-section
            // lines (with their group tree) so resolving is N+1-free.
            $items = OpportunityItem::query()
                ->where('opportunity_id', $opportunityId)
                ->whereNull('section_id')
                ->get();

            $products = Product::query()
                ->whereIn('id', $items
                    ->where('item_type', Product::class)
                    ->pluck('item_id')
                    ->filter()
                    ->unique()
                    ->all())
                ->with('productGroup.parent')
                ->get()
                ->keyBy('id')
                ->all();

            // The next sort_order sits after every existing section on this
            // opportunity so the auto groups append below any user sections.
            $nextOrder = (int) OpportunitySection::query()
                ->where('opportunity_id', $opportunityId)
                ->max('sort_order');

            $nextOrder = OpportunitySection::query()->where('opportunity_id', $opportunityId)->exists()
                ? $nextOrder + 1
                : 0;

            // Find-or-create one section per distinct bucket key, caching by key.
            $sectionByKey = [];

            foreach ($items as $item) {
                [$key, $label] = $resolver->resolve($item, $products);

                if (! isset($sectionByKey[$key])) {
                    $section = OpportunitySection::query()
                        ->where('opportunity_id', $opportunityId)
                        ->where('auto_group_key', $key)
                        ->first();

                    if ($section === null) {
                        $section = OpportunitySection::create([
                            'opportunity_id' => $opportunityId,
                            'parent_id' => null,
                            'auto_group_key' => $key,
                            'name' => $label,
                            'sort_order' => $nextOrder++,
                        ]);
                    }

                    $sectionByKey[$key] = $section;
                }

                $item->section_id = $sectionByKey[$key]->id;
                $item->saveQuietly();
            }
        }
    }

    /**
     * Best-effort reversal: drop the auto-group sections this backfill created and
     * return their lines to the null-section render path. User sections (NULL
     * `auto_group_key`) are untouched.
     */
    public function down(): void
    {
        $autoSectionIds = OpportunitySection::query()
            ->whereNotNull('auto_group_key')
            ->pluck('id');

        if ($autoSectionIds->isEmpty()) {
            return;
        }

        OpportunityItem::query()
            ->whereIn('section_id', $autoSectionIds)
            ->update(['section_id' => null]);

        OpportunitySection::query()
            ->whereIn('id', $autoSectionIds)
            ->delete();
    }
};
