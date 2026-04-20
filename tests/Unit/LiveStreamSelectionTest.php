<?php

use App\Support\LiveStreamRosterSelector;

test('teacher selection keeps pinned participants first', function () {
    $selector = new LiveStreamRosterSelector;

    $participants = collect(range(1, 12))
        ->map(fn (int $userId): array => ['id' => $userId, 'user_id' => $userId])
        ->all();

    $selected = $selector->forTeacher($participants, [5, 2], 1234);

    expect($selected)->toHaveCount(9);
    expect($selected[0]['user_id'])->toBe(5);
    expect($selected[1]['user_id'])->toBe(2);
});

test('teacher selection injects dominant speaker when not already pinned', function () {
    $selector = new LiveStreamRosterSelector;

    $participants = collect(range(1, 12))
        ->map(fn (int $userId): array => ['id' => $userId, 'user_id' => $userId])
        ->all();

    $selected = $selector->forTeacher($participants, [1, 2], 1234, 9);

    expect(collect($selected)->pluck('user_id')->all())->toContain(9);
});

test('viewer selection never exceeds five participants', function () {
    $selector = new LiveStreamRosterSelector;

    $participants = collect(range(1, 20))
        ->map(fn (int $userId): array => ['id' => $userId, 'user_id' => $userId])
        ->all();

    $selected = $selector->forViewer($participants, 4321);

    expect($selected)->toHaveCount(5);
});
