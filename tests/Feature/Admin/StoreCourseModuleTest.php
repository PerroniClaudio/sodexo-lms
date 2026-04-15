<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a module for the selected course', function () {
    $course = Course::factory()->create();

    $response = $this->post(route('admin.courses.modules.store', $course), [
        'type' => 'live',
        'title' => 'Modulo live introduttivo',
    ]);

    $module = Module::query()->firstOrFail();

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));
    $response->assertSessionHas('status', 'Modulo creato con successo.');

    expect($module->type)->toBe('live');
    expect($module->status)->toBe('draft');
    expect($module->order)->toBe(1);
    expect($module->belongsTo)->toBe((string) $course->getKey());
    expect($module->title)->toBe('Modulo live introduttivo');
});

it('validates the selected module type', function () {
    $course = Course::factory()->create();

    $response = $this->from(route('admin.courses.edit', $course))->post(route('admin.courses.modules.store', $course), [
        'type' => 'invalid-type',
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHasErrors(['type']);
});

it('requires a title for non quiz modules', function () {
    $course = Course::factory()->create();

    $response = $this->from(route('admin.courses.edit', $course))->post(route('admin.courses.modules.store', $course), [
        'type' => 'video',
        'title' => '',
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHasErrors(['title']);
});

it('uses the default title for quiz modules', function () {
    $course = Course::factory()->create();

    $response = $this->post(route('admin.courses.modules.store', $course), [
        'type' => 'learning_quiz',
    ]);

    $module = Module::query()->firstOrFail();

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    expect($module->title)->toBe('Quiz di apprendimento');
});
