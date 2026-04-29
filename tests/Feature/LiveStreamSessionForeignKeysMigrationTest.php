<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('live stream tables define the session foreign keys after migrations', function () {
    $handRaisesForeignKeys = collect(Schema::getForeignKeys('live_stream_hand_raises'))
        ->keyBy(fn (array $foreignKey): string => $foreignKey['columns'][0]);

    $participantsForeignKeys = collect(Schema::getForeignKeys('live_stream_participants'))
        ->keyBy(fn (array $foreignKey): string => $foreignKey['columns'][0]);

    expect($handRaisesForeignKeys['live_stream_session_id']['foreign_table'] ?? null)
        ->toBe('live_stream_sessions');

    expect($participantsForeignKeys['live_stream_session_id']['foreign_table'] ?? null)
        ->toBe('live_stream_sessions');
});
