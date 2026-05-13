<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('reorders course modules and updates every order value', function () {
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $secondModule = Module::factory()->create([
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $thirdModule = Module::factory()->create([
        'order' => 3,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->patchJson(route('admin.courses.modules.reorder', $course), [
        'modules' => [$thirdModule->id, $firstModule->id, $secondModule->id],
    ]);

    $response->assertOk();
    $response->assertJson([
        'message' => 'Ordine moduli aggiornato con successo.',
    ]);

    expect($thirdModule->fresh()->order)->toBe(1);
    expect($firstModule->fresh()->order)->toBe(2);
    expect($secondModule->fresh()->order)->toBe(3);
});

it('rejects invalid reorder payloads', function () {
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $secondModule = Module::factory()->create([
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $foreignModule = Module::factory()->create();

    $response = $this->patchJson(route('admin.courses.modules.reorder', $course), [
        'modules' => [$firstModule->id, $foreignModule->id],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['modules.1', 'modules']);

    expect($firstModule->fresh()->order)->toBe(1);
    expect($secondModule->fresh()->order)->toBe(2);
});

it('moves the satisfaction survey back to the last module during reorder', function () {
    $course = Course::factory()->create();
    $firstModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $surveyModule = Module::factory()->create([
        'type' => Module::TYPE_SATISFACTION_QUIZ,
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->patchJson(route('admin.courses.modules.reorder', $course), [
        'modules' => [$surveyModule->id, $firstModule->id],
    ]);

    $response->assertOk();

    expect($firstModule->fresh()->order)->toBe(1);
    expect($surveyModule->fresh()->order)->toBe(2);
});
