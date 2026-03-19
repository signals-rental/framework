<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phones', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('phones', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
