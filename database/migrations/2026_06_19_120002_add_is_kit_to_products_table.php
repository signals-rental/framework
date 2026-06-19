<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised `is_kit` flag on products.
 *
 * A product is a kit when it owns `serialised_components` rows. The flag is a
 * cheap, queryable mirror of that fact — set when a composition exists — so the
 * availability read path and demand resolver can route kit products without a
 * composition-existence subquery on every line. Composition existence remains the
 * source of truth; the flag is kept in step by the {@see Product}
 * `components()` lifecycle (and seeders/factories that build kits).
 *
 * Kits generate no demand of their own and hold no snapshot rows — availability
 * is composed read-time from components — so kit products should also carry
 * `track_availability = false`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_kit')->default(false)->after('track_availability');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_kit');
        });
    }
};
