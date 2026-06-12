<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_tutor_enrollments')) {
            return;
        }

        Schema::create('course_tutor_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['course_id', 'user_id', 'deleted_at']);
            $table->index(['course_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_tutor_enrollments');
    }
};
