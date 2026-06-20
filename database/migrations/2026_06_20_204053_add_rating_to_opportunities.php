<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RMS-parity `rating` header field on the opportunity (C3i).
 *
 * A simple nullable 0–5 priority/quality rating the sales team can set on an
 * opportunity (RMS `rating`). Nullable, so old event-sourced snapshots replay
 * unchanged. No workflow attaches to it — it is a plain editable header scalar
 * carried through the existing OpportunityCreated / OpportunityUpdated events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->nullable()->after('source_opportunity_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropColumn('rating');
        });
    }
};
