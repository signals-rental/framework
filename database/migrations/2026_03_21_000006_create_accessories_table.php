<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('accessory_product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->boolean('included')->default(true);
            $table->boolean('zero_priced')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'accessory_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessories');
    }
};
