<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('module_type');
            $table->smallInteger('field_type');
            $table->foreignId('custom_field_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('list_name_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->jsonb('settings')->nullable();
            $table->jsonb('validation_rules')->nullable();
            $table->jsonb('visibility_rules')->nullable();
            $table->text('default_value')->nullable();
            $table->string('plugin_name')->nullable();
            $table->string('document_layout_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'module_type']);
            $table->index('module_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
