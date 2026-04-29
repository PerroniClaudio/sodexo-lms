<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('live stream poll responses define the poll foreign key after migrations', function () {
    $pollResponseForeignKeys = collect(Schema::getForeignKeys('live_stream_poll_responses'))
        ->keyBy(fn (array $foreignKey): string => $foreignKey['columns'][0]);

    expect($pollResponseForeignKeys['live_stream_poll_id']['foreign_table'] ?? null)
        ->toBe('live_stream_polls');
});
