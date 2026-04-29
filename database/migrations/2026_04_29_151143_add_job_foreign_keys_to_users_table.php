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
        if (! Schema::hasTable('users')) {
            return;
        }

        $missingForeignKeys = $this->missingForeignKeys();

        if ($missingForeignKeys === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($missingForeignKeys) {
            foreach ($missingForeignKeys as $column => $foreignTable) {
                $table->foreign($column)->references('id')->on($foreignTable);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $existingForeignKeys = $this->existingForeignKeys();
        $jobColumns = array_keys($this->jobReferences());
        $foreignKeysToDrop = array_values(array_intersect($jobColumns, array_keys($existingForeignKeys)));

        if ($foreignKeysToDrop === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($foreignKeysToDrop) {
            foreach ($foreignKeysToDrop as $column) {
                $table->dropForeign([$column]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function jobReferences(): array
    {
        return [
            'job_unit_id' => 'job_units',
            'job_category_id' => 'job_categories',
            'job_level_id' => 'job_levels',
            'job_title_id' => 'job_titles',
            'job_role_id' => 'job_roles',
            'job_sector_id' => 'job_sectors',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function missingForeignKeys(): array
    {
        $existingForeignKeys = $this->existingForeignKeys();

        return array_filter(
            $this->jobReferences(),
            fn (string $foreignTable, string $column): bool => Schema::hasTable($foreignTable)
                && ! array_key_exists($column, $existingForeignKeys),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return array<string, string>
     */
    private function existingForeignKeys(): array
    {
        $foreignKeys = [];

        foreach (Schema::getForeignKeys('users') as $foreignKey) {
            $column = $foreignKey['columns'][0] ?? null;
            $foreignTable = $foreignKey['foreign_table'] ?? null;

            if (! is_string($column) || ! is_string($foreignTable)) {
                continue;
            }

            $foreignKeys[$column] = $foreignTable;
        }

        return $foreignKeys;
    }
};
