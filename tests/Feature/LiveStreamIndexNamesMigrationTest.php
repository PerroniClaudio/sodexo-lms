<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('live stream migrations define mysql safe index names', function () {
    $participantIndexes = collect(Schema::getIndexes('live_stream_participants'))->pluck('name');
    $attendanceIndexes = collect(Schema::getIndexes('live_stream_attendance_minutes'))->pluck('name');

    expect($participantIndexes)->toContain('ls_participants_session_user_unq');
    expect($participantIndexes)->toContain('ls_participants_session_twilio_unq');
    expect($participantIndexes)->toContain('ls_participants_session_role_hidden_idx');

    expect($attendanceIndexes)->toContain('ls_attendance_session_user_minute_unq');
    expect($attendanceIndexes)->toContain('ls_attendance_session_minute_idx');
});
