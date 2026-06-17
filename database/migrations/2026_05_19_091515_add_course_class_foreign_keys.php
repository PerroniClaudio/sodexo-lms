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
        foreach ($this->missingForeignKeys() as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->foreign($column)->references('id')->on('course_classes')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
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
            'course_class_users' => 'course_class_id',
            'course_class_teachers' => 'course_class_id',
            'course_class_tutors' => 'course_class_id',
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
                && Schema::hasTable('course_classes')
                && ! $this->hasCourseClassForeignKey($tableName, $column),
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
                && $this->hasCourseClassForeignKey($tableName, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function hasCourseClassForeignKey(string $tableName, string $column): bool
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (($foreignKey['columns'][0] ?? null) !== $column) {
                continue;
            }

            if (($foreignKey['foreign_table'] ?? null) === 'course_classes') {
                return true;
            }
        }

        return false;
    }
};
