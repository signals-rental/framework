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
        Schema::create('auto_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->unique()->constrained('custom_fields')->cascadeOnDelete();
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->integer('next_value')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_number_sequences');
    }
};
