<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->morphs('entity');
            $table->string('value_string')->nullable();
            $table->text('value_text')->nullable();
            $table->integer('value_integer')->nullable();
            $table->decimal('value_decimal', 16, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->time('value_time')->nullable();
            $table->jsonb('value_json')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'entity_type', 'entity_id']);
            $table->index(['custom_field_id', 'value_string']);
            $table->index(['custom_field_id', 'value_integer']);
            $table->index(['custom_field_id', 'value_decimal']);
            $table->index(['custom_field_id', 'value_boolean']);
            $table->index(['custom_field_id', 'value_date']);
            $table->index(['custom_field_id', 'value_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
