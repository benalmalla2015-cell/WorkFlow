<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('file_name');
            $table->string('original_name');
            $table->string('mime_type');
            $table->integer('file_size');
            $table->string('path');
            $table->enum('type', ['sales_upload', 'factory_upload'])->default('sales_upload');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
