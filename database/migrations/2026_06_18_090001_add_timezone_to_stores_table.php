<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an IANA timezone to stores.
 *
 * The availability engine aligns demand windows and snapshot slots to each
 * store's local midnight / quarter boundaries before storing them as UTC, so
 * every store needs its own timezone. Nullable with the application timezone as
 * the default; consumers fall back to `config('app.timezone')` when null. Slot
 * alignment itself lands in M2-6 — the column is added now because it is a
 * harmless, stable schema dependency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('country_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
