<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Core CRMS fields
            $table->boolean('bookable')->default(false)->after('is_active');
            $table->smallInteger('location_type')->default(0)->after('bookable');
            $table->integer('day_cost')->default(0)->after('location_type');
            $table->integer('hour_cost')->default(0)->after('day_cost');
            $table->integer('distance_cost')->default(0)->after('hour_cost');
            $table->integer('flat_rate_cost')->default(0)->after('distance_cost');
            $table->unsignedBigInteger('lawful_basis_type_id')->nullable()->after('flat_rate_cost');
            $table->foreign('lawful_basis_type_id')->references('id')->on('list_values')->nullOnDelete();

            // Rename sale tax class for CRMS compatibility
            $table->renameColumn('organisation_tax_class_id', 'sale_tax_class_id');
        });

        Schema::table('members', function (Blueprint $table) {
            // Purchase tax class (separate statement after rename)
            $table->unsignedBigInteger('purchase_tax_class_id')->nullable()->after('sale_tax_class_id');
            $table->foreign('purchase_tax_class_id')->references('id')->on('organisation_tax_classes')->nullOnDelete();

            $table->string('mapping_id')->nullable()->after('icon_thumb_url');

            // Organisation-specific membership fields
            $table->string('account_number')->nullable()->after('tag_list');
            $table->string('tax_number')->nullable()->after('account_number');
            $table->boolean('is_cash')->default(false)->after('tax_number');
            $table->boolean('is_on_stop')->default(false)->after('is_cash');
            $table->smallInteger('rating')->default(0)->after('is_on_stop');
            $table->unsignedBigInteger('owned_by')->nullable()->after('rating');
            $table->foreign('owned_by')->references('id')->on('members')->nullOnDelete();
            $table->unsignedBigInteger('price_category_id')->nullable()->after('owned_by');
            $table->unsignedBigInteger('discount_category_id')->nullable()->after('price_category_id');
            $table->unsignedBigInteger('invoice_term_id')->nullable()->after('discount_category_id');
            $table->foreign('invoice_term_id')->references('id')->on('list_values')->nullOnDelete();
            $table->integer('invoice_term_length')->default(0)->after('invoice_term_id');

            // Contact-specific membership fields
            $table->string('title', 50)->nullable()->after('invoice_term_length');
            $table->string('department')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['purchase_tax_class_id']);
            $table->dropForeign(['lawful_basis_type_id']);
            $table->dropForeign(['owned_by']);
            $table->dropForeign(['invoice_term_id']);

            $table->dropColumn([
                'bookable', 'location_type',
                'day_cost', 'hour_cost', 'distance_cost', 'flat_rate_cost',
                'lawful_basis_type_id', 'purchase_tax_class_id',
                'mapping_id',
                'account_number', 'tax_number', 'is_cash', 'is_on_stop',
                'rating', 'owned_by', 'price_category_id', 'discount_category_id',
                'invoice_term_id', 'invoice_term_length',
                'title', 'department',
            ]);

            $table->renameColumn('sale_tax_class_id', 'organisation_tax_class_id');
        });
    }
};
