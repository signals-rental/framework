<?php

use App\Verbs\Events\Opportunities\BulkQuantityDispatched;
use App\Verbs\Events\Opportunities\BulkQuantityReturned;
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
 *  - `dispatched_quantity` / `returned_quantity` advance via the
 *    {@see BulkQuantityDispatched} /
 *    {@see BulkQuantityReturned} events. The
 *    availability engine reads `effective_quantity = quantity - returned_quantity`.
 *
 * Serialised allocation is tracked per asset on `opportunity_item_assets`, so a
 * line-level allocated_quantity column is unnecessary — the §7.6 aggregate-status
 * derivation counts the per-asset assignment rows directly.
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
            $table->decimal('dispatched_quantity', 10, 2)->default(0)->after('ends_at');
            $table->decimal('returned_quantity', 10, 2)->default(0)->after('dispatched_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table): void {
            $table->dropColumn(['dispatched_quantity', 'returned_quantity']);
        });
    }
};
