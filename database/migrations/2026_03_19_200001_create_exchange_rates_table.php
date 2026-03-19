<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('source_currency_code', 3);
            $table->string('target_currency_code', 3);
            $table->decimal('rate', 18, 8);
            $table->decimal('inverse_rate', 18, 8);
            $table->string('source', 50);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['source_currency_code', 'target_currency_code', 'effective_at'], 'exchange_rates_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
