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
        Schema::create('video_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('appears_at_seconds')->default(0);
            $table->unsignedInteger('minimum_seconds')->default(0);
            $table->text('support_text_html')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('self_evaluation_disk')->nullable();
            $table->string('self_evaluation_path')->nullable();
            $table->string('self_evaluation_original_name')->nullable();
            $table->string('self_evaluation_mime_type')->nullable();
            $table->unsignedBigInteger('self_evaluation_size_bytes')->nullable();
            $table->timestamps();

            $table->index(['module_id', 'appears_at_seconds']);
        });

        Schema::create('video_exercise_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_exercise_id')->constrained('video_exercises')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('file');
            $table->string('title');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('youtube_url')->nullable();
            $table->text('content_html')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::create('video_exercise_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_exercise_id')->constrained('video_exercises')->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('minimum_characters')->default(1);
            $table->unsignedInteger('order')->default(1);
            $table->timestamps();

            $table->index(['video_exercise_id', 'order']);
        });

        Schema::create('video_exercise_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_exercise_id')->constrained('video_exercises')->cascadeOnDelete();
            $table->foreignId('course_user_id')->constrained('course_user')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('in_progress');
            $table->unsignedInteger('elapsed_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['video_exercise_id', 'course_user_id']);
            $table->index(['course_user_id', 'status']);
        });

        Schema::create('video_exercise_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_exercise_submission_id')->constrained('video_exercise_submissions')->cascadeOnDelete();
            $table->foreignId('video_exercise_question_id')->constrained('video_exercise_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->timestamps();

            $table->unique(['video_exercise_submission_id', 'video_exercise_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_exercise_answers');
        Schema::dropIfExists('video_exercise_submissions');
        Schema::dropIfExists('video_exercise_questions');
        Schema::dropIfExists('video_exercise_materials');
        Schema::dropIfExists('video_exercises');
    }
};
