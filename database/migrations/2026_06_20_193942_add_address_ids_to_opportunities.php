<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery + collection address FKs on the opportunity (C-data-2, decision D1).
 *
 * Each references the polymorphic `addresses` table (an opportunity's
 * delivery/collection address is one of the member's addresses). Both nullable
 * so old event-sourced snapshots replay unchanged; `nullOnDelete` keeps the
 * opportunity alive if the referenced address is later removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('delivery_address_id')
                ->nullable()
                ->after('collection_instructions')
                ->constrained('addresses')
                ->nullOnDelete();

            $table->foreignId('collection_address_id')
                ->nullable()
                ->after('delivery_address_id')
                ->constrained('addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            // Drop each FK (and its backing index) before its column.
            $table->dropConstrainedForeignId('delivery_address_id');
            $table->dropConstrainedForeignId('collection_address_id');
        });
    }
};
