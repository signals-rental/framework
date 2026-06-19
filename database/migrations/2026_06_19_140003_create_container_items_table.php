<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The `container_items` table — membership rows linking a serialised item to the
 * container that currently houses it (serialised-containers.md §Data Model →
 * Container Items Table).
 *
 * A row is "active" while `unpacked_at IS NULL`; closing it (unpack/transfer/
 * dissolve) sets `unpacked_at` + `unpacked_reason`. This doubles as the per-item
 * audit trail (who packed it, when, why it left).
 *
 * Driver-aware partial unique index: on PostgreSQL a serialised item can have at
 * most one ACTIVE membership (`unpacked_at IS NULL`). On SQLite the partial
 * predicate is unsupported, so the table is created without it and the invariant
 * is enforced in the pack action — mirroring the `demands`/`containers` degraded
 * path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('container_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('container_id')
                ->constrained('containers')
                ->cascadeOnDelete();

            // The packed serialised item (a serialised stock level).
            $table->foreignId('serialised_item_id')
                ->constrained('stock_levels')
                ->cascadeOnDelete();

            // Denormalised for grouped product-quantity validation queries.
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->timestampTz('packed_at');
            $table->foreignId('packed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('unpacked_at')->nullable();
            // dissolved | manual | transferred.
            $table->string('unpacked_reason', 20)->nullable();
            $table->foreignId('transferred_to_container_id')
                ->nullable()
                ->constrained('containers')
                ->nullOnDelete();

            // Forward-compat base-schema columns (serialised-containers.md §Data
            // Model). Written by Phase-4 auto-return operations (a dissolve-on-
            // dispatch returns a packed item to the opportunity it belongs to);
            // present in the base schema so Phase 4 needs no further migration.
            $table->foreignId('auto_returned_from_opportunity_id')
                ->nullable()
                ->constrained('opportunities')
                ->nullOnDelete();
            $table->foreignId('returned_from_opportunity_id')
                ->nullable()
                ->constrained('opportunities')
                ->nullOnDelete();

            $table->string('position')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('container_id', 'idx_container_items_container');
            $table->index(['container_id', 'product_id'], 'idx_container_items_container_product');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            // SQLite degraded path: a non-partial index for membership lookups;
            // the "one active membership per item" invariant is enforced in the
            // pack action.
            Schema::table('container_items', function (Blueprint $table): void {
                $table->index('serialised_item_id', 'idx_container_items_serialised_item');
            });

            return;
        }

        // PostgreSQL: one ACTIVE membership per serialised item.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_container_items_active_membership
              ON container_items (serialised_item_id)
              WHERE unpacked_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('container_items');
    }
};
