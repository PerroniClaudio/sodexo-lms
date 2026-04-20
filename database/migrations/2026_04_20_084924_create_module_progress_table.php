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
        Schema::create('module_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_user_id')->constrained('course_user')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('status')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedInteger('video_current_second')->nullable();
            $table->unsignedInteger('video_max_second')->nullable();
            $table->unsignedSmallInteger('quiz_attempts')->default(0);
            $table->unsignedInteger('quiz_score')->nullable();
            $table->unsignedInteger('quiz_total_score')->nullable();
            $table->timestamp('passed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_user_id', 'module_id']);
            $table->index(['module_id', 'status']);
            $table->index(['course_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_user');
    }
};
