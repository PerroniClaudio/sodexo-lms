<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('language_levels')->doesntExist()) {
            DB::table('language_levels')->insert([
                ['name' => 'a1', 'sort_order' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'a2', 'sort_order' => 2, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'b1', 'sort_order' => 3, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'b2', 'sort_order' => 4, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'c1', 'sort_order' => 5, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'c2', 'sort_order' => 6, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (Schema::hasTable('courses')) {
            $missingCourseForeignKeys = $this->missingForeignKeysForTable('courses', [
                'required_language_level_id' => 'language_levels',
                'grants_language_level_id' => 'language_levels',
            ]);

            if ($missingCourseForeignKeys !== []) {
                Schema::table('courses', function (Blueprint $table) use ($missingCourseForeignKeys): void {
                    if (array_key_exists('required_language_level_id', $missingCourseForeignKeys)) {
                        $table->foreign('required_language_level_id')
                            ->references('id')
                            ->on('language_levels')
                            ->restrictOnDelete();
                    }

                    if (array_key_exists('grants_language_level_id', $missingCourseForeignKeys)) {
                        $table->foreign('grants_language_level_id')
                            ->references('id')
                            ->on('language_levels')
                            ->nullOnDelete();
                    }
                });
            }
        }

        if (Schema::hasTable('users')) {
            $missingUserForeignKeys = $this->missingForeignKeysForTable('users', [
                'declared_language_level_id' => 'language_levels',
                'verified_language_level_id' => 'language_levels',
            ]);

            if ($missingUserForeignKeys !== []) {
                Schema::table('users', function (Blueprint $table) use ($missingUserForeignKeys): void {
                    if (array_key_exists('declared_language_level_id', $missingUserForeignKeys)) {
                        $table->foreign('declared_language_level_id')
                            ->references('id')
                            ->on('language_levels')
                            ->nullOnDelete();
                    }

                    if (array_key_exists('verified_language_level_id', $missingUserForeignKeys)) {
                        $table->foreign('verified_language_level_id')
                            ->references('id')
                            ->on('language_levels')
                            ->nullOnDelete();
                    }
                });
            }
        }

        if (Schema::hasTable('course_user') && Schema::hasTable('courses')) {
            $missingCourseUserForeignKeys = $this->missingForeignKeysForTable('course_user', [
                'origin_course_id' => 'courses',
            ]);

            if ($missingCourseUserForeignKeys !== []) {
                Schema::table('course_user', function (Blueprint $table): void {
                    $table->foreign('origin_course_id')
                        ->references('id')
                        ->on('courses')
                        ->nullOnDelete();
                });
            }
        }

        $defaultLanguageLevelId = DB::table('language_levels')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->value('id');

        if ($defaultLanguageLevelId !== null) {
            DB::table('courses')
                ->whereNull('required_language_level_id')
                ->update(['required_language_level_id' => $defaultLanguageLevelId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('course_user') && $this->hasForeignKey('course_user', 'origin_course_id', 'courses')) {
            Schema::table('course_user', function (Blueprint $table): void {
                $table->dropForeign(['origin_course_id']);
            });
        }

        if (Schema::hasTable('users')) {
            $existingUserForeignKeys = $this->existingForeignKeys('users');

            if (array_key_exists('declared_language_level_id', $existingUserForeignKeys)
                || array_key_exists('verified_language_level_id', $existingUserForeignKeys)) {
                Schema::table('users', function (Blueprint $table) use ($existingUserForeignKeys): void {
                    if (array_key_exists('declared_language_level_id', $existingUserForeignKeys)) {
                        $table->dropForeign(['declared_language_level_id']);
                    }

                    if (array_key_exists('verified_language_level_id', $existingUserForeignKeys)) {
                        $table->dropForeign(['verified_language_level_id']);
                    }
                });
            }
        }

        if (Schema::hasTable('courses')) {
            $existingCourseForeignKeys = $this->existingForeignKeys('courses');

            if (array_key_exists('required_language_level_id', $existingCourseForeignKeys)
                || array_key_exists('grants_language_level_id', $existingCourseForeignKeys)) {
                Schema::table('courses', function (Blueprint $table) use ($existingCourseForeignKeys): void {
                    if (array_key_exists('required_language_level_id', $existingCourseForeignKeys)) {
                        $table->dropForeign(['required_language_level_id']);
                    }

                    if (array_key_exists('grants_language_level_id', $existingCourseForeignKeys)) {
                        $table->dropForeign(['grants_language_level_id']);
                    }
                });
            }
        }
    }

    /**
     * @param  array<string, string>  $expectedReferences
     * @return array<string, string>
     */
    private function missingForeignKeysForTable(string $tableName, array $expectedReferences): array
    {
        $existingForeignKeys = $this->existingForeignKeys($tableName);

        return array_filter(
            $expectedReferences,
            fn (string $foreignTable, string $column): bool => Schema::hasTable($foreignTable)
                && ! array_key_exists($column, $existingForeignKeys),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, string>
     */
    private function existingForeignKeys(string $tableName): array
    {
        $foreignKeys = [];

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            $column = $foreignKey['columns'][0] ?? null;
            $foreignTable = $foreignKey['foreign_table'] ?? null;

            if (! is_string($column) || ! is_string($foreignTable)) {
                continue;
            }

            $foreignKeys[$column] = $foreignTable;
        }

        return $foreignKeys;
    }

    private function hasForeignKey(string $tableName, string $column, string $foreignTable): bool
    {
        $existingForeignKeys = $this->existingForeignKeys($tableName);

        return ($existingForeignKeys[$column] ?? null) === $foreignTable;
    }
};
