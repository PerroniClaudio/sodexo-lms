<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_quiz_submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_quiz_submission_id');
            $table->foreignId('module_quiz_question_id')
                ->constrained('module_quiz_questions')
                ->cascadeOnDelete();
            $table->foreignId('module_quiz_answer_id')
                ->nullable()
                ->constrained('module_quiz_answers')
                ->nullOnDelete();
            $table->unsignedSmallInteger('question_number');
            $table->string('selected_option_key', 1)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamps();

            $table->unique(['module_quiz_submission_id', 'module_quiz_question_id'], 'quiz_submission_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_quiz_submission_answers');
    }
};
