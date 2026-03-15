<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_tax_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tax_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->constrained()->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['organisation_tax_class_id', 'product_tax_class_id', 'tax_rate_id'],
                'tax_rules_class_rate_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
