<?php

use Illuminate\Support\Facades\Schema;

it('can safely resume the certificate sequence migration', function () {
    $migration = require database_path('migrations/2026_07_31_151332_add_certificate_sequence_to_course_user_table.php');

    $migration->up();
    $migration->up();

    expect(Schema::hasColumn('course_user', 'certificate_sequence'))->toBeTrue()
        ->and(Schema::hasColumn('course_user', 'certificate_sequence_year'))->toBeTrue()
        ->and(Schema::hasIndex('course_user', ['certificate_sequence_year', 'certificate_sequence'], 'unique'))->toBeTrue();
});
