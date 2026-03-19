<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_views', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('entity_type', 100);
            $table->string('visibility', 20)->default('personal');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->jsonb('columns');
            $table->jsonb('filters')->default('[]');
            $table->string('sort_column', 100)->nullable();
            $table->string('sort_direction', 4)->default('asc');
            $table->integer('per_page')->default(20);
            $table->jsonb('config')->default('{}');
            $table->timestamps();

            $table->index(['entity_type', 'visibility']);
            $table->index(['entity_type', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_views');
    }
};
