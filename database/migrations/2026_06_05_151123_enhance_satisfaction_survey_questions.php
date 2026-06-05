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
        Schema::table('satisfaction_survey_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('satisfaction_survey_questions', 'input_type')) {
                $table->string('input_type', 32)->default('radio')->after('text');
            }

            if (! Schema::hasColumn('satisfaction_survey_questions', 'excluded_course_types')) {
                $table->json('excluded_course_types')->nullable()->after('input_type');
            }

            if (! Schema::hasColumn('satisfaction_survey_questions', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('satisfaction_survey_submission_answers', function (Blueprint $table): void {
            if (! Schema::hasColumn('satisfaction_survey_submission_answers', 'open_text')) {
                $table->text('open_text')->nullable()->after('satisfaction_survey_answer_id');
            }
        });

        Schema::table('satisfaction_survey_submission_answers', function (Blueprint $table): void {
            $table->foreignId('satisfaction_survey_answer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('satisfaction_survey_submission_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('satisfaction_survey_submission_answers', 'open_text')) {
                $table->dropColumn('open_text');
            }

            $table->foreignId('satisfaction_survey_answer_id')->nullable(false)->change();
        });

        Schema::table('satisfaction_survey_questions', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('satisfaction_survey_questions', 'excluded_course_types') ? 'excluded_course_types' : null,
                Schema::hasColumn('satisfaction_survey_questions', 'input_type') ? 'input_type' : null,
                Schema::hasColumn('satisfaction_survey_questions', 'deleted_at') ? 'deleted_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
