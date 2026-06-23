<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THROWAWAY "Editor Lab" prototype table.
 *
 * A flat, Current-RMS-style line-item grid used only by the four prototype
 * opportunity editors (jquery / sortable-tree / sortablejs / local-first).
 * Every row is one item distinguished by `item_type`; nesting + order are
 * encoded in the materialized `path` string (4-char zero-padded segment per
 * level, lexical sort = tree pre-order). The `prototype` column scopes each
 * prototype's own private copy of the seeded tree so the four editors never
 * collide.
 *
 * NOT part of the event-sourced opportunity backend — disposable scratch data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prototype_opportunity_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('opportunity_id')
                ->constrained('opportunities')
                ->cascadeOnDelete();

            // Which prototype OWNS this copy of the tree:
            // 'jquery' | 'sortable-tree' | 'sortablejs' | 'local-first'.
            $table->string('prototype')->index();

            // 'group' | 'product' | 'accessory' | 'service'
            $table->string('item_type');

            // Materialized tree path: 4-char zero-padded segment per level.
            // e.g. "0001" (depth 1), "00010001" (depth 2). Lexical sort = pre-order.
            $table->string('path')->index();

            $table->unsignedBigInteger('revenue_group_id')->nullable();

            $table->string('name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->integer('days')->default(1);
            $table->integer('unit_price')->default(0);      // minor units
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->integer('charge_total')->default(0);    // minor units

            $table->string('type_label')->nullable();       // 'Rental' / 'Sale' / 'Service'
            $table->string('status_label')->nullable();     // 'Reserved' / 'Booked Out' / 'Prepared' / ...
            $table->boolean('is_collapsed')->default(false);

            $table->timestamps();

            $table->index(['opportunity_id', 'prototype', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prototype_opportunity_items');
    }
};
