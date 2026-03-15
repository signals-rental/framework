<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('list_names', function (Blueprint $table) {
            $table->string('plugin_name')->nullable();
        });

        Schema::table('list_values', function (Blueprint $table) {
            $table->string('plugin_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('list_names', function (Blueprint $table) {
            $table->dropColumn('plugin_name');
        });

        Schema::table('list_values', function (Blueprint $table) {
            $table->dropColumn('plugin_name');
        });
    }
};
