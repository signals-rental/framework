<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('calculation_strategy');
            $table->string('base_period')->nullable();
            $table->jsonb('enabled_modifiers')->default('[]');
            $table->jsonb('strategy_config')->default('{}');
            $table->jsonb('modifier_configs')->default('{}');
            $table->boolean('is_preset')->default(false);
            $table->string('preset_slug')->nullable()->unique();
            $table->foreignId('cloned_from_id')->nullable()->constrained('rate_definitions')->nullOnDelete();
            $table->timestamps();

            $table->index('is_preset');
            $table->index('calculation_strategy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_definitions');
    }
};
