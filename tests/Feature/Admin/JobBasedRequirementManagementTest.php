<?php

use App\Models\JobBasedRequirement;
use App\Models\JobRole;
use App\Models\JobTask;
use Database\Seeders\RoleAndPermissionSeeder;

it('stores a job-based requirement from admin area', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    actingAsRole('admin');

    $primaryRole = JobRole::factory()->create(['name' => 'Dirigente']);
    $secondaryRole = JobRole::factory()->create(['name' => 'Responsabile']);
    $task = JobTask::factory()->create();

    $response = $this->post(route('admin.job-based-requirements.store'), [
        'name' => 'Obbligo Antincendio Livello 2',
        'description' => 'Test requirement',
        'is_active' => '1',
        'rules_json' => json_encode([
            [
                [
                    'field' => 'job_role_id',
                    'operator' => 'IN',
                    'value' => [$secondaryRole->getKey(), $primaryRole->getKey()],
                ],
                [
                    'field' => 'job_task_id',
                    'operator' => 'IN',
                    'value' => [$task->getKey()],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('job_based_requirements', [
        'name' => 'Obbligo Antincendio Livello 2',
        'is_active' => true,
    ]);

    expect(JobBasedRequirement::query()->firstOrFail()->rules)->toBe([
        [
            [
                'field' => 'job_role_id',
                'operator' => 'IN',
                'value' => [$primaryRole->getKey(), $secondaryRole->getKey()],
            ],
            [
                'field' => 'job_task_id',
                'operator' => 'IN',
                'value' => [$task->getKey()],
            ],
        ],
    ]);
});
