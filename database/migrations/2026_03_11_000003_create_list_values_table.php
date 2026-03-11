<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_name_id')->constrained('list_names')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('list_values')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['list_name_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_values');
    }
};
