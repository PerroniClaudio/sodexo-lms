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
        if (! Schema::hasColumn('courses', 'has_satisfaction_survey')) {
            Schema::table('courses', function (Blueprint $table): void {
                $table->boolean('has_satisfaction_survey')->default(false)->after('status');
            });
        }

        if (! Schema::hasColumn('courses', 'satisfaction_survey_required_for_certificate')) {
            Schema::table('courses', function (Blueprint $table): void {
                $table->boolean('satisfaction_survey_required_for_certificate')->default(false)->after('has_satisfaction_survey');
            });
        }

        if (! Schema::hasTable('satisfaction_survey_templates')) {
            Schema::create('satisfaction_survey_templates', function (Blueprint $table): void {
                $table->id();
                $table->boolean('is_active')->default(false)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('satisfaction_survey_questions')) {
            Schema::create('satisfaction_survey_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('satisfaction_survey_template_id')
                    ->constrained('satisfaction_survey_templates', indexName: 'survey_questions_template_fk')
                    ->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('text');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('satisfaction_survey_answers')) {
            Schema::create('satisfaction_survey_answers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('satisfaction_survey_question_id')
                    ->constrained('satisfaction_survey_questions', indexName: 'survey_answers_question_fk')
                    ->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('text');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('satisfaction_survey_submissions')) {
            Schema::create('satisfaction_survey_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('satisfaction_survey_template_id')
                    ->constrained('satisfaction_survey_templates', indexName: 'survey_submissions_template_fk')
                    ->restrictOnDelete();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
                $table->timestamp('submitted_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('satisfaction_survey_submission_answers')) {
            Schema::create('satisfaction_survey_submission_answers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('satisfaction_survey_submission_id')
                    ->constrained('satisfaction_survey_submissions', indexName: 'survey_submission_answers_submission_fk')
                    ->cascadeOnDelete();
                $table->foreignId('satisfaction_survey_question_id')
                    ->constrained('satisfaction_survey_questions', indexName: 'survey_submission_answers_question_fk')
                    ->restrictOnDelete();
                $table->foreignId('satisfaction_survey_answer_id')
                    ->constrained('satisfaction_survey_answers', indexName: 'survey_submission_answers_answer_fk')
                    ->restrictOnDelete();
                $table->timestamps();

                $table->unique(
                    ['satisfaction_survey_submission_id', 'satisfaction_survey_question_id'],
                    'satisfaction_submission_question_unique'
                );
            });
        }
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

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('courses', 'has_satisfaction_survey') ? 'has_satisfaction_survey' : null,
            Schema::hasColumn('courses', 'satisfaction_survey_required_for_certificate') ? 'satisfaction_survey_required_for_certificate' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('courses', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
