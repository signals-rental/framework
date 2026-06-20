<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->restrictOnDelete();
            $table->string('role', 100)->nullable();
            $table->boolean('mute')->default(false);
            $table->timestamps();

            $table->unique(['opportunity_id', 'member_id']);
            $table->index('opportunity_id');
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_participants');
    }
};
