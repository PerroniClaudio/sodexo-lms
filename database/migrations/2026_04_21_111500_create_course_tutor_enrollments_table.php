<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_tutor_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'course_id', 'deleted_at']);
            $table->index(['course_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_tutor_enrollments');
    }
};
