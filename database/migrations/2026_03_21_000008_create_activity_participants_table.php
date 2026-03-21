<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members');
            $table->boolean('mute')->default(false);
            $table->timestamps();

            $table->unique(['activity_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_participants');
    }
};
