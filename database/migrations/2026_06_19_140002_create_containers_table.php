<?php

use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The operational `containers` table (serialised-containers.md §Data Model →
 * Containers Table). A container is a serialised asset (or temporary barcode)
 * that houses other serialised assets as one dispatchable unit.
 *
 * This is the operational overlay on top of the CRMS-compat
 * `stock_levels.container_stock_level_id` / `container_mode` columns; both are
 * kept conceptually in sync. Containers are plain Eloquent — NOT event-sourced.
 *
 * Driver-aware partial unique index: on PostgreSQL a single asset can house only
 * one non-dissolved container at a time, enforced with a partial unique index.
 * On SQLite (the default test suite) that partial predicate is unsupported, so
 * the table is created without it and the uniqueness is enforced in the pack
 * action — mirroring the `demands` migration's degraded path.
 *
 * For the M5-3b availability subset only the `open` / `sealed` statuses are
 * exercised; the `dispatched` / `returned` / `dissolved` states and their
 * timestamp columns are provisioned for the Phase-4 lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');

            // The serialised housing asset. Null for temporary (barcode-only)
            // containers. References stock_levels (the serialised item table).
            $table->foreignId('serialised_item_id')
                ->nullable()
                ->constrained('stock_levels')
                ->nullOnDelete();

            // The containerable product backing this container. Null for temporary.
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Nesting hierarchy (Phase-4). Self-referential.
            $table->foreignId('parent_container_id')
                ->nullable()
                ->constrained('containers')
                ->nullOnDelete();

            // Repack lineage (Phase-4): links a repacked container to its
            // dissolved predecessor.
            $table->foreignId('previous_container_id')
                ->nullable()
                ->constrained('containers')
                ->nullOnDelete();

            $table->boolean('is_temporary')->default(false);
            $table->string('barcode')->nullable();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            $table->string('scan_mode', 20)->default(ContainerScanMode::Strict->value);
            $table->string('status', 20)->default(ContainerStatus::Open->value);

            // Lifecycle timestamps — only open/sealed are exercised in M5-3b; the
            // rest are Phase-4.
            $table->timestampTz('sealed_at')->nullable();
            $table->foreignId('sealed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('unsealed_at')->nullable();
            $table->foreignId('unsealed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dissolved_at')->nullable();
            $table->foreignId('dissolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dissolved_reason', 20)->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('returned_at')->nullable();

            $table->foreignId('opportunity_id')
                ->nullable()
                ->constrained('opportunities')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('status', 'idx_containers_status');
            $table->index('opportunity_id', 'idx_containers_opportunity');
            $table->index('product_id', 'idx_containers_product');
            $table->index('parent_container_id', 'idx_containers_parent');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            // SQLite degraded path: a non-partial index suffices for lookups; the
            // "one active container per housing item" invariant is enforced in the
            // pack action rather than at the DB level.
            Schema::table('containers', function (Blueprint $table): void {
                $table->index('serialised_item_id', 'idx_containers_serialised_item');
            });

            return;
        }

        // --- PostgreSQL: real partial guarantees. ---

        // A self-nest is logically impossible.
        DB::statement(<<<'SQL'
            ALTER TABLE containers
              ADD CONSTRAINT chk_containers_no_self_nest
              CHECK (parent_container_id IS NULL OR parent_container_id <> id)
        SQL);

        // One ACTIVE (non-dissolved) container per housing item.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_containers_active_serialised_item
              ON containers (serialised_item_id)
              WHERE serialised_item_id IS NOT NULL AND status <> 'dissolved'
        SQL);

        // Unique active temporary-container barcode.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_containers_active_temporary_barcode
              ON containers (barcode)
              WHERE is_temporary = true AND barcode IS NOT NULL AND status <> 'dissolved'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
