<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('rate_definition_id')->constrained('rate_definitions');
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('transaction_type');
            $table->integer('price');
            $table->string('currency', 3);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();

            // Overlaps are intentional; resolution is priority-based at the app layer
            // (no unique constraint). These indexes back the resolver lookups.
            $table->index(['product_id', 'store_id', 'transaction_type', 'priority']);
            $table->index(['product_id', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_rates');
    }
};
