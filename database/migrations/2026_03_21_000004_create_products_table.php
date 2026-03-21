<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('product_group_id')->nullable()->constrained('product_groups')->nullOnDelete();
            $table->string('product_type', 20)->default('rental');
            $table->smallInteger('allowed_stock_type')->default(1);
            $table->smallInteger('stock_method')->default(1);
            $table->decimal('weight', 10, 4)->nullable();
            $table->string('barcode')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->integer('replacement_charge')->default(0);
            $table->decimal('buffer_percent', 5, 2)->default(0);
            $table->integer('post_rent_unavailability')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('accessory_only')->default(false);
            $table->boolean('system')->default(false);
            $table->boolean('discountable')->default(true);
            $table->foreignId('tax_class_id')->nullable()->constrained('product_tax_classes')->nullOnDelete();
            $table->foreignId('purchase_tax_class_id')->nullable()->constrained('product_tax_classes')->nullOnDelete();
            $table->foreignId('rental_revenue_group_id')->nullable()->constrained('revenue_groups')->nullOnDelete();
            $table->foreignId('sale_revenue_group_id')->nullable()->constrained('revenue_groups')->nullOnDelete();
            $table->foreignId('sub_rental_cost_group_id')->nullable()->constrained('cost_groups')->nullOnDelete();
            $table->integer('sub_rental_price')->default(0);
            $table->foreignId('purchase_cost_group_id')->nullable()->constrained('cost_groups')->nullOnDelete();
            $table->integer('purchase_price')->default(0);
            $table->foreignId('country_of_origin_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->jsonb('tag_list')->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('icon_thumb_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
