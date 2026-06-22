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
        Schema::create('training_path_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_path_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type')->default('document');
            $table->string('category');
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_path_documents');
    }
};
