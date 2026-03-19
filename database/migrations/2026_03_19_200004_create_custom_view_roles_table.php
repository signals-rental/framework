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
        Schema::create('custom_view_roles', function (Blueprint $table) {
            $table->foreignId('custom_view_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['custom_view_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_view_roles');
    }
};
