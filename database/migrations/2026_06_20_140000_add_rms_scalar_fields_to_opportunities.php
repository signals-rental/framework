<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RMS-parity scalar header fields on the opportunity (C-data-1).
 *
 * Adds the full event-logistics lifecycle date set (prep → load → deliver →
 * setup → show → takedown → collect → unload → deprep, each a start/end pair),
 * the two quotation/order milestone datetimes (`ordered_at`, `quote_invalid_at`),
 * the chargeable-days + open-ended-rental controls, the customer
 * collecting/returning flags, the delivery/collection free-text instructions, and
 * the `source_opportunity_id` clone-lineage FK (previously audit-only).
 *
 * Every column is nullable or carries a safe default, so old event-sourced
 * snapshots replay unchanged. Money is unaffected here — `chargeable_days` is a
 * plain decimal day count, not a money value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            // C3a — event-logistics lifecycle dates (UTC), all nullable, in
            // physical-workflow order. Each phase is a start/end pair.
            $table->dateTime('prep_starts_at')->nullable()->after('charge_ends_at');
            $table->dateTime('prep_ends_at')->nullable()->after('prep_starts_at');
            $table->dateTime('load_starts_at')->nullable()->after('prep_ends_at');
            $table->dateTime('load_ends_at')->nullable()->after('load_starts_at');
            $table->dateTime('deliver_starts_at')->nullable()->after('load_ends_at');
            $table->dateTime('deliver_ends_at')->nullable()->after('deliver_starts_at');
            $table->dateTime('setup_starts_at')->nullable()->after('deliver_ends_at');
            $table->dateTime('setup_ends_at')->nullable()->after('setup_starts_at');
            $table->dateTime('show_starts_at')->nullable()->after('setup_ends_at');
            $table->dateTime('show_ends_at')->nullable()->after('show_starts_at');
            $table->dateTime('takedown_starts_at')->nullable()->after('show_ends_at');
            $table->dateTime('takedown_ends_at')->nullable()->after('takedown_starts_at');
            $table->dateTime('collect_starts_at')->nullable()->after('takedown_ends_at');
            $table->dateTime('collect_ends_at')->nullable()->after('collect_starts_at');
            $table->dateTime('unload_starts_at')->nullable()->after('collect_ends_at');
            $table->dateTime('unload_ends_at')->nullable()->after('unload_starts_at');
            $table->dateTime('deprep_starts_at')->nullable()->after('unload_ends_at');
            $table->dateTime('deprep_ends_at')->nullable()->after('deprep_starts_at');

            // C3a — milestone datetimes.
            $table->dateTime('ordered_at')->nullable()->after('deprep_ends_at');
            $table->dateTime('quote_invalid_at')->nullable()->after('ordered_at');

            // C3b — chargeable-days + open-ended-rental controls.
            $table->boolean('use_chargeable_days')->default(false)->after('quote_invalid_at');
            $table->decimal('chargeable_days', 8, 1)->nullable()->after('use_chargeable_days');
            $table->boolean('open_ended_rental')->default(false)->after('chargeable_days');

            // C3c — customer collecting/returning flags.
            $table->boolean('customer_collecting')->default(false)->after('open_ended_rental');
            $table->boolean('customer_returning')->default(false)->after('customer_collecting');

            // Delivery / collection free-text instructions.
            $table->text('delivery_instructions')->nullable()->after('customer_returning');
            $table->text('collection_instructions')->nullable()->after('delivery_instructions');

            // C3e — clone-lineage FK to the source opportunity. nullOnDelete keeps
            // the clone alive if the source is later removed.
            $table->foreignId('source_opportunity_id')
                ->nullable()
                ->after('collection_instructions')
                ->constrained('opportunities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            // Drop the FK (and its backing index) before the column.
            $table->dropConstrainedForeignId('source_opportunity_id');

            $table->dropColumn([
                'prep_starts_at',
                'prep_ends_at',
                'load_starts_at',
                'load_ends_at',
                'deliver_starts_at',
                'deliver_ends_at',
                'setup_starts_at',
                'setup_ends_at',
                'show_starts_at',
                'show_ends_at',
                'takedown_starts_at',
                'takedown_ends_at',
                'collect_starts_at',
                'collect_ends_at',
                'unload_starts_at',
                'unload_ends_at',
                'deprep_starts_at',
                'deprep_ends_at',
                'ordered_at',
                'quote_invalid_at',
                'use_chargeable_days',
                'chargeable_days',
                'open_ended_rental',
                'customer_collecting',
                'customer_returning',
                'delivery_instructions',
                'collection_instructions',
            ]);
        });
    }
};
