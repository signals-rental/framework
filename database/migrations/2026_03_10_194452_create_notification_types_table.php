<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('category');
            $table->string('name');
            $table->string('description')->nullable();
            $table->jsonb('available_channels');
            $table->jsonb('default_channels');
            $table->boolean('is_active')->default(true);
            $table->string('source', 50)->default('core');
            $table->timestamps();
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_type_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('channels')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_type_id')->constrained()->cascadeOnDelete();
            $table->jsonb('channels')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notification_types');
    }
};
