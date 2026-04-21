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
        Schema::create('scorm_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->string('identifier')->nullable();
            $table->string('entry_point')->nullable();
            $table->string('file_path');
            $table->string('extracted_path')->nullable();
            $table->json('manifest_data')->nullable();
            $table->json('sco_data')->nullable();
            $table->string('status')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'module_id']);
            $table->index(['module_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorm_packages');
    }
};
