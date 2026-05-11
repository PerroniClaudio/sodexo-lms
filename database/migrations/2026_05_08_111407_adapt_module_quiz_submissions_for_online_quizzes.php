<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'module_quiz_submissions';

    private const ONLINE_INDEX = 'mqs_course_module_created_idx';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'source_type')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('source_type')->default('upload')->after('module_id');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'course_enrollment_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreignId('course_enrollment_id')->nullable()->after('user_id')->constrained('course_user')->cascadeOnDelete();
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
            $table->string('path')->nullable()->change();
        });

        if (! Schema::hasColumn(self::TABLE, 'started_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->timestamp('started_at')->nullable()->after('processed_at');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'submitted_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->timestamp('submitted_at')->nullable()->after('started_at');
            });
        }

        if (! Schema::hasIndex(self::TABLE, ['course_enrollment_id', 'module_id', 'created_at'])) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['course_enrollment_id', 'module_id', 'created_at'], self::ONLINE_INDEX);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, ['course_enrollment_id', 'module_id', 'created_at'])) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::ONLINE_INDEX);
            });
        }

        if ($this->hasCourseEnrollmentForeignKey()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropForeign(['course_enrollment_id']);
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn(self::TABLE, 'source_type') ? 'source_type' : null,
            Schema::hasColumn(self::TABLE, 'course_enrollment_id') ? 'course_enrollment_id' : null,
            Schema::hasColumn(self::TABLE, 'started_at') ? 'started_at' : null,
            Schema::hasColumn(self::TABLE, 'submitted_at') ? 'submitted_at' : null,
        ]));

        if ($columns !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable(false)->change();
            $table->string('path')->nullable(false)->change();
        });
    }

    private function hasCourseEnrollmentForeignKey(): bool
    {
        foreach (Schema::getForeignKeys(self::TABLE) as $foreignKey) {
            if (($foreignKey['columns'][0] ?? null) !== 'course_enrollment_id') {
                continue;
            }

            if (($foreignKey['foreign_table'] ?? null) === 'course_user') {
                return true;
            }
        }

        return false;
    }
};
