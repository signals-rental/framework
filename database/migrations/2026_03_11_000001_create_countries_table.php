<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('code3', 3)->unique();
            $table->string('name');
            $table->string('currency_code', 3)->nullable();
            $table->string('phone_prefix', 10)->nullable();
            $table->string('default_timezone', 100)->nullable();
            $table->string('default_date_format', 20)->nullable();
            $table->string('default_time_format', 20)->nullable();
            $table->string('default_number_format', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
