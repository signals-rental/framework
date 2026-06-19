<?php

use App\Enums\KitComponentBinding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kit composition (bill-of-materials) table.
 *
 * Each row links a kit parent product to one component product and the quantity
 * of that component per single kit. A product becomes "a kit" when it owns one or
 * more rows here (see also the denormalised `products.is_kit` flag).
 *
 * `binding` distinguishes pool components (drawn from general stock per job, the
 * only binding the catalogue-kit chunk M5-3a handles) from fixed components
 * (permanently container-assigned — modelled in M5-3b). The spec names this table
 * `serialised_components` (data-model-implementation.md, Phase 3); the column
 * schema is derived here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serialised_components', function (Blueprint $table): void {
            $table->id();

            // The kit parent product this component belongs to.
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // The component product drawn into the kit.
            $table->foreignId('component_product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // Units of the component per single kit (decimal for fractional/bulk).
            $table->decimal('quantity', 12, 4)->default(1);

            // pool (drawn from general stock) | fixed (container-assigned, M5-3b).
            $table->string('binding', 20)->default(KitComponentBinding::Pool->value);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // One row per (kit, component): a component cannot appear twice in a
            // kit — adjust its quantity instead.
            $table->unique(['product_id', 'component_product_id'], 'uq_kit_component');

            $table->index('component_product_id', 'idx_kit_component_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serialised_components');
    }
};
