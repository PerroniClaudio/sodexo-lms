<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispense_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained('course_user')->cascadeOnDelete();
            $table->foreignId('module_teaching_material_id')->constrained()->cascadeOnDelete();
            $table->timestamp('downloaded_at');
            $table->timestamps();

            $table->unique(['course_enrollment_id', 'module_teaching_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispense_downloads');
    }
};
