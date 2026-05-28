<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('users table defines all job foreign keys after migrations', function () {
    expect(Schema::hasColumn('users', 'job_task_id'))->toBeTrue();
    expect(Schema::hasColumn('users', 'job_title_id'))->toBeFalse();

    $foreignKeys = [];

    foreach (Schema::getForeignKeys('users') as $foreignKey) {
        $column = $foreignKey['columns'][0] ?? null;
        $foreignTable = $foreignKey['foreign_table'] ?? null;

        if (! is_string($column) || ! is_string($foreignTable)) {
            continue;
        }

        $foreignKeys[$column] = $foreignTable;
    }

    expect($foreignKeys['job_unit_id'] ?? null)->toBe('job_units');
    expect($foreignKeys['job_category_id'] ?? null)->toBe('job_categories');
    expect($foreignKeys['job_level_id'] ?? null)->toBe('job_levels');
    expect($foreignKeys['job_task_id'] ?? null)->toBe('job_tasks');
    expect($foreignKeys['job_role_id'] ?? null)->toBe('job_roles');
    expect($foreignKeys['job_sector_id'] ?? null)->toBe('job_sectors');
});
