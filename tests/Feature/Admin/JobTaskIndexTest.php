<?php

use App\Models\JobTask;

it('uses compact pagination for job tasks', function () {
    actingAsRole('admin');

    JobTask::factory()->count(70)->create(['code' => null]);

    $response = $this->get(route('admin.job-tasks.index'));
    $lastPage = $response->viewData('tasks')->lastPage();

    $response
        ->assertSuccessful()
        ->assertSee('aria-label="Precedente"', escape: false)
        ->assertSee('aria-label="Successiva"', escape: false)
        ->assertSee('aria-label="Vai alla pagina"', escape: false)
        ->assertSee('href="'.route('admin.job-tasks.index', ['page' => 1]).'"', escape: false)
        ->assertSee('href="'.route('admin.job-tasks.index', ['page' => 3]).'"', escape: false)
        ->assertSee('href="'.route('admin.job-tasks.index', ['page' => $lastPage]).'"', escape: false)
        ->assertDontSee('href="'.route('admin.job-tasks.index', ['page' => 4]).'"', escape: false);
});
