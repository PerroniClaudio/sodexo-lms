<?php

use App\Jobs\RefreshAllJobBasedRequirementsJob;
use App\Models\JobBasedRequirement;
use App\Models\JobRole;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('refreshes one user job-based requirements from admin api', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = actingAsRole('admin');
    $role = JobRole::factory()->create();
    $user = User::factory()->asUser()->create([
        'job_role_id' => $role->getKey(),
    ]);

    JobBasedRequirement::factory()->create([
        'rules' => [
            [
                [
                    'field' => 'job_role_id',
                    'operator' => '===',
                    'value' => $role->getKey(),
                ],
            ],
        ],
    ]);

    $response = $this->postJson(route('admin.api.users.job-based-requirements.refresh', $user));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.active_requirements');

    expect($user->fresh()->requirements_last_calculated_at)->not->toBeNull();
});

it('queues a full refresh from admin api', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    actingAsRole('admin');
    Queue::fake();

    $response = $this->postJson(route('admin.api.job-based-requirements.refresh'));

    $response
        ->assertStatus(202)
        ->assertJsonPath('success', true);

    Queue::assertPushed(RefreshAllJobBasedRequirementsJob::class);
});
