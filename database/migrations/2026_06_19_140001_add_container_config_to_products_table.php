<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Container configuration columns on the existing `products` table
 * (serialised-containers.md §Data Model → Product Fields).
 *
 * A product is "containerable" (its serialised instances can act as a container
 * housing) when `is_containerable` is true. The remaining columns configure the
 * container behaviour — packing template, availability mode, nesting depth,
 * check-in scanning, repack-on-return, and warehouse-add settings — and apply to
 * every serialised instance of the product.
 *
 * M5-3b only exercises the availability-relevant subset (`is_containerable`,
 * `container_template`, `container_availability_mode`). The other columns are
 * provisioned here so the full Phase-4 lifecycle has its schema in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Enables container behaviour for this product's serialised instances.
            $table->boolean('is_containerable')->default(false)->after('is_kit');

            // Strict packing specification — { slots: [{product_id, quantity,
            // binding}], max_items }. Validated on save (Phase-4 CRUD).
            $table->jsonb('container_template')->nullable()->after('is_containerable');

            // kit | transport | hybrid — controls availability + lifecycle.
            $table->string('container_availability_mode', 20)
                ->nullable()
                ->after('container_template');

            // parent | individual | parent_then_verify — check-in scanning
            // (kit/hybrid only). Nullable for transport-mode products.
            $table->string('container_checkin_mode', 30)
                ->nullable()
                ->after('container_availability_mode');

            // Max nesting levels when this product is the outermost container.
            $table->unsignedInteger('container_max_nesting_depth')
                ->default(2)
                ->after('container_checkin_mode');

            // Trigger the repack workflow when this container is returned (Phase-4).
            $table->boolean('container_repack_on_return')
                ->default(false)
                ->after('container_max_nesting_depth');

            // Auto-addable to opportunities at dispatch (Phase-4 dispatch).
            $table->boolean('is_warehouse_addable')
                ->default(false)
                ->after('container_repack_on_return');

            // Default charge when auto-added, in minor units (pence/cents).
            $table->integer('warehouse_add_default_charge')
                ->nullable()
                ->after('is_warehouse_addable');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'is_containerable',
                'container_template',
                'container_availability_mode',
                'container_checkin_mode',
                'container_max_nesting_depth',
                'container_repack_on_return',
                'is_warehouse_addable',
                'warehouse_add_default_charge',
            ]);
        });
    }
};
