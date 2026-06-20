<?php

use App\Services\What3WordsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * what3words three-word address on every address (C-data-2).
 *
 * Nullable free-text (e.g. "filled.count.soap"), resolved to coordinates via
 * {@see What3WordsService} when the integration is configured. Used
 * by member addresses and, by extension, the opportunity delivery/collection
 * address pickers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('what3words')->nullable()->after('postcode');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn('what3words');
        });
    }
};
