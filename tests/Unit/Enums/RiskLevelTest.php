<?php

use App\Enums\RiskLevel;

test('order values are sequential and ascending', function () {
    expect(RiskLevel::LOW->order())->toBe(1)
        ->and(RiskLevel::MEDIUM->order())->toBe(2)
        ->and(RiskLevel::HIGH->order())->toBe(3);
});

test('isHigherThan compares correctly', function () {
    expect(RiskLevel::HIGH->isHigherThan(RiskLevel::MEDIUM))->toBeTrue()
        ->and(RiskLevel::HIGH->isHigherThan(RiskLevel::LOW))->toBeTrue()
        ->and(RiskLevel::MEDIUM->isHigherThan(RiskLevel::LOW))->toBeTrue()
        ->and(RiskLevel::LOW->isHigherThan(RiskLevel::MEDIUM))->toBeFalse()
        ->and(RiskLevel::LOW->isHigherThan(RiskLevel::HIGH))->toBeFalse()
        ->and(RiskLevel::MEDIUM->isHigherThan(RiskLevel::MEDIUM))->toBeFalse();
});

test('isLowerThan compares correctly', function () {
    expect(RiskLevel::LOW->isLowerThan(RiskLevel::MEDIUM))->toBeTrue()
        ->and(RiskLevel::LOW->isLowerThan(RiskLevel::HIGH))->toBeTrue()
        ->and(RiskLevel::MEDIUM->isLowerThan(RiskLevel::HIGH))->toBeTrue()
        ->and(RiskLevel::HIGH->isLowerThan(RiskLevel::MEDIUM))->toBeFalse()
        ->and(RiskLevel::HIGH->isLowerThan(RiskLevel::LOW))->toBeFalse()
        ->and(RiskLevel::MEDIUM->isLowerThan(RiskLevel::MEDIUM))->toBeFalse();
});

test('isAtLeast compares correctly', function () {
    expect(RiskLevel::HIGH->isAtLeast(RiskLevel::HIGH))->toBeTrue()
        ->and(RiskLevel::HIGH->isAtLeast(RiskLevel::MEDIUM))->toBeTrue()
        ->and(RiskLevel::MEDIUM->isAtLeast(RiskLevel::MEDIUM))->toBeTrue()
        ->and(RiskLevel::MEDIUM->isAtLeast(RiskLevel::LOW))->toBeTrue()
        ->and(RiskLevel::LOW->isAtLeast(RiskLevel::MEDIUM))->toBeFalse();
});

test('isAtMost compares correctly', function () {
    expect(RiskLevel::LOW->isAtMost(RiskLevel::LOW))->toBeTrue()
        ->and(RiskLevel::LOW->isAtMost(RiskLevel::MEDIUM))->toBeTrue()
        ->and(RiskLevel::MEDIUM->isAtMost(RiskLevel::MEDIUM))->toBeTrue()
        ->and(RiskLevel::MEDIUM->isAtMost(RiskLevel::HIGH))->toBeTrue()
        ->and(RiskLevel::HIGH->isAtMost(RiskLevel::MEDIUM))->toBeFalse();
});

test('max returns the higher risk level', function () {
    expect(RiskLevel::HIGH->max(RiskLevel::LOW))->toBe(RiskLevel::HIGH)
        ->and(RiskLevel::LOW->max(RiskLevel::HIGH))->toBe(RiskLevel::HIGH)
        ->and(RiskLevel::MEDIUM->max(RiskLevel::LOW))->toBe(RiskLevel::MEDIUM)
        ->and(RiskLevel::MEDIUM->max(RiskLevel::MEDIUM))->toBe(RiskLevel::MEDIUM);
});

test('min returns the lower risk level', function () {
    expect(RiskLevel::HIGH->min(RiskLevel::LOW))->toBe(RiskLevel::LOW)
        ->and(RiskLevel::LOW->min(RiskLevel::HIGH))->toBe(RiskLevel::LOW)
        ->and(RiskLevel::MEDIUM->min(RiskLevel::HIGH))->toBe(RiskLevel::MEDIUM)
        ->and(RiskLevel::MEDIUM->min(RiskLevel::MEDIUM))->toBe(RiskLevel::MEDIUM);
});

test('ordered returns all levels in correct order', function () {
    $ordered = RiskLevel::ordered();

    expect($ordered)->toHaveCount(3)
        ->and($ordered[0])->toBe(RiskLevel::LOW)
        ->and($ordered[1])->toBe(RiskLevel::MEDIUM)
        ->and($ordered[2])->toBe(RiskLevel::HIGH);
});

test('labels are in Italian', function () {
    expect(RiskLevel::LOW->label())->toBe('Rischio Basso')
        ->and(RiskLevel::MEDIUM->label())->toBe('Rischio Medio')
        ->and(RiskLevel::HIGH->label())->toBe('Rischio Alto');
});

test('badge colors are appropriate for risk levels', function () {
    expect(RiskLevel::LOW->badgeColor())->toBe('badge-success')
        ->and(RiskLevel::MEDIUM->badgeColor())->toBe('badge-warning')
        ->and(RiskLevel::HIGH->badgeColor())->toBe('badge-error');
});
