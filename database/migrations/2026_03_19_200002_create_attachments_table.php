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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('original_name', 255);
            $table->string('file_path', 500);
            $table->string('thumb_path', 500)->nullable();
            $table->string('disk', 50)->default('s3');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->string('category', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('scan_status', 20)->default('clean');
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
