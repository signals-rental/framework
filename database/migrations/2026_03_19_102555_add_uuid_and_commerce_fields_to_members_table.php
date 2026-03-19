<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('peppol_id')->nullable()->after('tax_number');
            $table->string('chamber_of_commerce_number')->nullable()->after('peppol_id');
            $table->string('global_location_number')->nullable()->after('chamber_of_commerce_number');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['peppol_id', 'chamber_of_commerce_number', 'global_location_number']);
        });
    }
};
