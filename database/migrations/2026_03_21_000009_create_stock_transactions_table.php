<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_level_id')->constrained('stock_levels')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->integer('transaction_type');
            $table->timestamp('transaction_at');
            $table->decimal('quantity', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('manual')->default(true);
            $table->timestamps();

            $table->index('stock_level_id');
            $table->index('store_id');
            $table->index('transaction_type');
            $table->index('transaction_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
