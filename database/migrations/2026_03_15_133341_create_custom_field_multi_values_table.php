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
        Schema::create('custom_field_multi_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_value_id')->constrained('custom_field_values')->cascadeOnDelete();
            $table->foreignId('list_value_id')->constrained('list_values')->cascadeOnDelete();
            $table->unique(['custom_field_value_id', 'list_value_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_multi_values');
    }
};
