<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective migration: `currency_code` + `exchange_rate` were added to
 * `opportunity_versions` by editing the original create migration (M1–M5 audit R3)
 * rather than adding a new one. Databases that ran the original create therefore
 * lack the columns, while fresh (migrate:fresh) databases already have them.
 *
 * Both adds are guarded by `hasColumn`, so this is a clean no-op on a fresh
 * database and an additive fix on any incrementally-migrated database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunity_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('opportunity_versions', 'currency_code')) {
                $table->string('currency_code', 3)->nullable()->after('label');
            }

            if (! Schema::hasColumn('opportunity_versions', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('opportunity_versions', function (Blueprint $table): void {
            if (Schema::hasColumn('opportunity_versions', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }

            if (Schema::hasColumn('opportunity_versions', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
        });
    }
};
