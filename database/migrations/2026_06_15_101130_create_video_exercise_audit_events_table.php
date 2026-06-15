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
        Schema::create('video_exercise_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_exercise_id')->constrained('video_exercises')->cascadeOnDelete();
            $table->foreignId('video_exercise_submission_id')->nullable()->constrained('video_exercise_submissions')->nullOnDelete();
            $table->foreignId('course_user_id')->constrained('course_user')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type');
            $table->unsignedInteger('completion_percentage')->default(0);
            $table->unsignedInteger('elapsed_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('updated_at_snapshot')->nullable();
            $table->timestamps();

            $table->index(['video_exercise_id', 'event_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_exercise_audit_events');
    }
};
