<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->unsignedBigInteger('regarding_id')->nullable();
            $table->string('regarding_type', 255)->nullable();
            $table->foreignId('owned_by')->constrained('users');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(1);
            $table->integer('type_id')->default(1001);
            $table->integer('status_id')->default(2001);
            $table->boolean('completed')->default(false);
            $table->integer('time_status')->default(0);
            $table->jsonb('tag_list')->nullable();
            $table->timestamps();

            $table->index(['regarding_type', 'regarding_id']);
            $table->index('owned_by');
            $table->index('type_id');
            $table->index('status_id');
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
