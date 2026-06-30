<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('creates a module for the selected course', function () {
    $course = Course::factory()->create([
        'type' => 'blended',
    ]);

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
    $course = Course::factory()->create([
        'type' => 'blended',
    ]);

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

it('keeps the satisfaction survey at the end when a new module is created', function () {
    $course = Course::factory()->create([
        'type' => 'blended',
    ]);
    $firstModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $surveyModule = Module::factory()->create([
        'type' => Module::TYPE_SATISFACTION_QUIZ,
        'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->post(route('admin.courses.modules.store', $course), [
        'type' => 'live',
        'title' => 'Modulo live introduttivo',
    ]);

    $newModule = Module::query()
        ->where('belongsTo', (string) $course->getKey())
        ->where('type', 'live')
        ->firstOrFail();

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $newModule]));

    expect($firstModule->fresh()->order)->toBe(1);
    expect($newModule->fresh()->order)->toBe(2);
    expect($surveyModule->fresh()->order)->toBe(3);
});

it('redirects back with an error flash when trying to create a module for a published course', function () {
    $course = Course::factory()->create([
        'type' => 'blended',
        'status' => 'published',
    ]);

    $response = $this
        ->from(route('admin.courses.edit', $course))
        ->post(route('admin.courses.modules.store', $course), [
            'type' => 'live',
            'title' => 'Modulo bloccato',
        ]);

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('error', 'Non è possibile modificare il modulo perché il corso associato è pubblicato.');

    expect(Module::query()->count())->toBe(0);
});

it('redirects with an error flash when trying to delete a published module', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'status' => 'published',
    ]);

    $response = $this->delete(route('admin.courses.modules.destroy', [$course, $module]));

    $response->assertRedirect(route('admin.courses.edit', $course));
    $response->assertSessionHas('error', 'Non è possibile eliminare un modulo pubblicato.');

    expect($module->fresh())->not->toBeNull();
    expect($module->fresh()?->trashed())->toBeFalse();
});

it('rejects module types that are not allowed for the course type', function () {
    $course = Course::factory()->create([
        'type' => 'fad',
    ]);

    $response = $this
        ->from(route('admin.courses.edit', [$course, 'section' => 'modules']))
        ->post(route('admin.courses.modules.store', $course), [
            'type' => Module::TYPE_VIDEO,
            'title' => 'Modulo video bloccato',
        ]);

    $response
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'modules']))
        ->assertSessionHasErrors([
            'type' => 'Il corso con tipologia FAD non può contenere un modulo Video.',
        ]);

    expect(Module::query()->count())->toBe(0);
});

it('shows restricted module types in the creation modal with an explanatory tooltip', function () {
    $course = Course::factory()->create([
        'type' => 'fad',
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'modules']));

    $response->assertOk()
        ->assertSee('data-tip="Il corso con tipologia FAD non può contenere un modulo Video."', false)
        ->assertSee('tooltip tooltip-bottom', false)
        ->assertSee('border-error/60 bg-error/10 text-error', false);
});

it('shows translated course type in the badge instead of raw database value', function () {
    $course = Course::factory()->create([
        'type' => 'async',
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'modules']));

    $response->assertOk()
        ->assertSeeText('Tipologia: FAD Asincrona')
        ->assertDontSeeText('Tipologia: async');
});

it('shows the scorm module limit note in the course modules section', function () {
    $course = Course::factory()->create([
        'type' => 'blended',
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'modules']));

    $response->assertOk()
        ->assertSeeText('Per i contenuti SCORM il corso può avere un solo modulo SCORM.')
        ->assertSeeText('Nota SCORM: per ogni corso è consentito un solo modulo SCORM.');
});

it('rejects creation of a second scorm module in the same course', function () {
    $course = Course::factory()->create([
        'type' => 'blended',
    ]);

    Module::factory()->create([
        'type' => Module::TYPE_SCORM,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this
        ->from(route('admin.courses.edit', [$course, 'section' => 'modules']))
        ->post(route('admin.courses.modules.store', $course), [
            'type' => Module::TYPE_SCORM,
            'title' => 'Secondo modulo SCORM',
        ]);

    $response
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'modules']))
        ->assertSessionHasErrors([
            'type' => 'Il corso può contenere un solo modulo SCORM.',
        ]);

    expect($course->modules()->where('type', Module::TYPE_SCORM)->count())->toBe(1);
});
