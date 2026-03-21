<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('item_name')->nullable();
            $table->string('asset_number')->nullable()->index();
            $table->string('serial_number')->nullable()->index();
            $table->string('barcode')->nullable()->index();
            $table->string('location')->nullable();
            $table->smallInteger('stock_type')->default(1);
            $table->smallInteger('stock_category')->default(10);
            $table->decimal('quantity_held', 10, 2)->default(0);
            $table->decimal('quantity_allocated', 10, 2)->default(0);
            $table->decimal('quantity_unavailable', 10, 2)->default(0);
            $table->decimal('quantity_on_order', 10, 2)->default(0);
            $table->foreignId('container_stock_level_id')->nullable()->constrained('stock_levels')->nullOnDelete();
            $table->string('container_mode')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_count_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
