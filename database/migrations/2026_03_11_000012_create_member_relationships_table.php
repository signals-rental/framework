<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_member_id')->constrained('members')->cascadeOnDelete();
            $table->string('relationship_type')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['member_id', 'related_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_relationships');
    }
};
