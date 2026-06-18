<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The resolution-to-opportunity-item pivot (shortage-resolution-sub-hires.md
 * §8.2). The many-to-many bridge tracking how much of a resolution serves each
 * line item. The core framework creates one row per resolution (one resolution,
 * one item); the batch-resolution plugin creates one-to-many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortage_resolution_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shortage_resolution_id')
                ->constrained('shortage_resolutions')
                ->cascadeOnDelete();
            $table->foreignId('opportunity_item_id')
                ->constrained('opportunity_items')
                ->cascadeOnDelete();
            $table->integer('quantity_allocated')->default(0);
            $table->timestampsTz();

            $table->index('opportunity_item_id', 'idx_shortage_resolution_items_item');
            $table->unique(
                ['shortage_resolution_id', 'opportunity_item_id'],
                'uq_shortage_resolution_items_resolution_item',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortage_resolution_items');
    }
};
