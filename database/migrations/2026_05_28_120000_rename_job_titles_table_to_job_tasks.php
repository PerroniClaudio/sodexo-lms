<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameUsersJobTitleColumnToJobTaskColumn();

        if (Schema::hasTable('job_titles') && ! Schema::hasTable('job_tasks')) {
            Schema::rename('job_titles', 'job_tasks');
        }

        if (Schema::hasTable('job_tasks') && ! Schema::hasColumn('job_tasks', 'code')) {
            Schema::table('job_tasks', function (Blueprint $table) {
                $table->string('code')->nullable()->after('name');
            });
        }

        $this->refreshUsersJobTaskForeignKey('job_titles', 'job_tasks');
    }

    public function down(): void
    {
        $this->refreshUsersJobTaskForeignKey('job_tasks', 'job_titles');

        if (Schema::hasTable('job_tasks') && Schema::hasColumn('job_tasks', 'code')) {
            Schema::table('job_tasks', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }

        if (Schema::hasTable('job_tasks') && ! Schema::hasTable('job_titles')) {
            Schema::rename('job_tasks', 'job_titles');
        }

        $this->renameUsersJobTaskColumnToJobTitleColumn();
    }

    private function renameUsersJobTitleColumnToJobTaskColumn(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'job_title_id')
            || Schema::hasColumn('users', 'job_task_id')) {
            return;
        }

        $this->dropUsersForeignKeyIfExists('job_title_id');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('job_title_id', 'job_task_id');
        });
    }

    private function renameUsersJobTaskColumnToJobTitleColumn(): void
    {
        if (! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'job_task_id')
            || Schema::hasColumn('users', 'job_title_id')) {
            return;
        }

        $this->dropUsersForeignKeyIfExists('job_task_id');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('job_task_id', 'job_title_id');
        });
    }

    private function refreshUsersJobTaskForeignKey(string $fromTable, string $toTable): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable($toTable) || ! Schema::hasColumn('users', 'job_task_id')) {
            return;
        }

        $currentForeignTable = null;

        foreach (Schema::getForeignKeys('users') as $foreignKey) {
            $column = $foreignKey['columns'][0] ?? null;
            $foreignTable = $foreignKey['foreign_table'] ?? null;

            if ($column === 'job_task_id' && is_string($foreignTable)) {
                $currentForeignTable = $foreignTable;

                break;
            }
        }

        if ($currentForeignTable === $toTable) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($currentForeignTable, $fromTable, $toTable) {
            if ($currentForeignTable === $fromTable || $currentForeignTable === $toTable) {
                $table->dropForeign(['job_task_id']);
            }

            $table->foreign('job_task_id')->references('id')->on($toTable);
        });
    }

    private function dropUsersForeignKeyIfExists(string $column): void
    {
        $hasForeignKey = collect(Schema::getForeignKeys('users'))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['columns'][0] ?? null) === $column);

        if (! $hasForeignKey) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });
    }
};
