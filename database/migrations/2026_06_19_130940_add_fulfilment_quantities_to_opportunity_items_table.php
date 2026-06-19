<?php

use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use App\Verbs\Events\Opportunities\BulkQuantityReturned;
use App\Verbs\States\OpportunityItemState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the bulk-quantity fulfilment tracking columns to `opportunity_items`
 * (opportunity-lifecycle.md §5.5). For serialised lines the dispatch/return cycle
 * is tracked per asset on `opportunity_item_assets`; for bulk (non-serialised)
 * lines there are no per-asset rows, so partial dispatch/return is tracked as
 * aggregate quantities on the line itself.
 *
 *  - `allocated_quantity` mirrors the {@see OpportunityItemState}
 *    property already maintained for serialised allocation; it is projected here so
 *    the §7.6 aggregate-status derivation can read every line uniformly.
 *  - `dispatched_quantity` / `returned_quantity` advance via the
 *    {@see BulkQuantityDispatched} /
 *    {@see BulkQuantityReturned} events. The
 *    availability engine reads `effective_quantity = quantity - returned_quantity`.
 *
 * Decimal(10,2) to match the line `quantity` column (bulk cable/rope lines book
 * fractional metres). Mutations flow through Verbs events whose handle() methods
 * dual-write these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->decimal('allocated_quantity', 10, 2)->default(0)->after('ends_at');
            $table->decimal('dispatched_quantity', 10, 2)->default(0)->after('allocated_quantity');
            $table->decimal('returned_quantity', 10, 2)->default(0)->after('dispatched_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn(['allocated_quantity', 'dispatched_quantity', 'returned_quantity']);
        });
    }
};
