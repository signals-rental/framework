<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('membership_type');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->string('locale')->nullable();
            $table->string('default_currency_code', 3)->nullable();
            $table->foreignId('organisation_tax_class_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('tag_list')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('icon_thumb_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('membership_type');
            $table->index('is_active');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
