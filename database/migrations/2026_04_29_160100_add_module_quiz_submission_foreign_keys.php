<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->missingForeignKeys() as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->foreign($column)->references('id')->on('module_quiz_submissions')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablesWithForeignKeys() as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });
        }
    }

    /**
     * @return array<string, string>
     */
    private function expectedReferences(): array
    {
        return [
            'module_quiz_submission_answers' => 'module_quiz_submission_id',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function missingForeignKeys(): array
    {
        return array_filter(
            $this->expectedReferences(),
            fn (string $column, string $tableName): bool => Schema::hasTable($tableName)
                && Schema::hasTable('module_quiz_submissions')
                && ! $this->hasModuleQuizSubmissionForeignKey($tableName, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, string>
     */
    private function tablesWithForeignKeys(): array
    {
        return array_filter(
            $this->expectedReferences(),
            fn (string $column, string $tableName): bool => Schema::hasTable($tableName)
                && $this->hasModuleQuizSubmissionForeignKey($tableName, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function hasModuleQuizSubmissionForeignKey(string $tableName, string $column): bool
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (($foreignKey['columns'][0] ?? null) !== $column) {
                continue;
            }

            if (($foreignKey['foreign_table'] ?? null) === 'module_quiz_submissions') {
                return true;
            }
        }

        return false;
    }
};
