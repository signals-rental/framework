<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an explicit before-buffer (prep) duration in minutes to products.
 *
 * The availability engine bakes both buffers into a demand's `period` range:
 *
 *   period = [starts_at - buffer_before_minutes, ends_at + buffer_after_minutes]
 *
 * The after-buffer (turnaround / post-rent unavailability) is already modelled
 * by the existing integer `products.post_rent_unavailability` column (minutes),
 * which the demand resolver uses as `buffer_after_minutes`. There was no
 * dedicated before-buffer column, so this adds one — an integer minute count,
 * default 0 — mirroring `post_rent_unavailability`'s semantics on the other end
 * of the window. This is clearer than overloading the percentage-based
 * `buffer_percent`, which serves a different (rate-padding) purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->integer('buffer_before_minutes')->default(0)->after('buffer_percent');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('buffer_before_minutes');
        });
    }
};
