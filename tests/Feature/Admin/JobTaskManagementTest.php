<?php

use App\Models\JobTask;

beforeEach(function () {
    actingAsRole('superadmin');
});

it('creates a job task without code', function () {
    $response = $this->post(route('admin.job-tasks.store'), [
        'name' => 'Operatore mensa',
        'description' => 'Gestione operativa del servizio',
    ]);

    $task = JobTask::query()->first();

    $response->assertRedirect(route('admin.job-tasks.edit', $task));

    expect($task)
        ->not->toBeNull()
        ->and($task->name)->toBe('Operatore mensa')
        ->and($task->code)->toBeNull();
});

it('updates a job task code when provided', function () {
    $task = JobTask::factory()->create([
        'name' => 'Addetto cucina',
        'code' => null,
    ]);

    $response = $this->put(route('admin.job-tasks.update', $task), [
        'name' => 'Addetto cucina',
        'code' => 'KIT_100',
        'description' => 'Supporto operativo in cucina',
    ]);

    $response->assertRedirect(route('admin.job-tasks.edit', $task));

    expect($task->fresh()->code)->toBe('KIT_100');
});
