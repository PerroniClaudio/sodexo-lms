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
        Schema::table('courses', function (Blueprint $table): void {
            $table->boolean('has_satisfaction_survey')->default(false)->after('status');
            $table->boolean('satisfaction_survey_required_for_certificate')->default(false)->after('has_satisfaction_survey');
        });

        Schema::create('satisfaction_survey_templates', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('satisfaction_survey_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('satisfaction_survey_template_id')
                ->constrained('satisfaction_survey_templates')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('satisfaction_survey_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('satisfaction_survey_question_id')
                ->constrained('satisfaction_survey_questions')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('text');
            $table->timestamps();
        });

        Schema::create('satisfaction_survey_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('satisfaction_survey_template_id')
                ->constrained('satisfaction_survey_templates')
                ->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('satisfaction_survey_submission_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('satisfaction_survey_submission_id')
                ->constrained('satisfaction_survey_submissions')
                ->cascadeOnDelete();
            $table->foreignId('satisfaction_survey_question_id')
                ->constrained('satisfaction_survey_questions')
                ->restrictOnDelete();
            $table->foreignId('satisfaction_survey_answer_id')
                ->constrained('satisfaction_survey_answers')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['satisfaction_survey_submission_id', 'satisfaction_survey_question_id'],
                'satisfaction_submission_question_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('satisfaction_survey_submission_answers');
        Schema::dropIfExists('satisfaction_survey_submissions');
        Schema::dropIfExists('satisfaction_survey_answers');
        Schema::dropIfExists('satisfaction_survey_questions');
        Schema::dropIfExists('satisfaction_survey_templates');

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'has_satisfaction_survey',
                'satisfaction_survey_required_for_certificate',
            ]);
        });
    }
};
