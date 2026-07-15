<?php

use App\Models\JobBasedRequirement;
use App\Models\JobRole;
use App\Models\JobTask;
use App\Models\User;
use App\Services\JobBasedRequirementEngineService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;

it('stores active and future job-based requirements for a user', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $matchingRole = JobRole::factory()->create();
    $currentTask = JobTask::factory()->create();
    $futureTask = JobTask::factory()->create();

    $user = User::factory()->asUser()->create([
        'job_role_id' => $matchingRole->getKey(),
        'employment_start_date' => today()->subMonth(),
    ]);

    $user->jobTasks()->sync([
        $currentTask->getKey() => [
            'starts_at' => today()->subMonth()->toDateString(),
            'ends_at' => null,
        ],
        $futureTask->getKey() => [
            'starts_at' => today()->addMonth()->toDateString(),
            'ends_at' => null,
        ],
    ]);

    $activeRequirement = JobBasedRequirement::factory()->create([
        'rules' => [
            [
                [
                    'field' => 'job_role_id',
                    'operator' => '===',
                    'value' => $matchingRole->getKey(),
                ],
            ],
        ],
    ]);

    $futureRequirement = JobBasedRequirement::factory()->create([
        'rules' => [
            [
                [
                    'field' => 'job_task_id',
                    'operator' => 'IN',
                    'value' => [$futureTask->getKey()],
                ],
            ],
        ],
    ]);

    app(JobBasedRequirementEngineService::class)->recalculateUser($user->fresh(['jobTasks']));

    expect(DB::table('job_based_requirement_user')
        ->where('user_id', $user->getKey())
        ->where('job_based_requirement_id', $activeRequirement->getKey())
        ->value('is_active'))->toBe(1);

    expect(DB::table('job_based_requirement_user')
        ->where('user_id', $user->getKey())
        ->where('job_based_requirement_id', $futureRequirement->getKey())
        ->value('is_active'))->toBe(0);

    expect(DB::table('job_based_requirement_user')
        ->where('user_id', $user->getKey())
        ->where('job_based_requirement_id', $futureRequirement->getKey())
        ->value('valid_from'))->toBe(today()->addMonth()->toDateString());
});

it('promotes due future requirements without full recalculation', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->asUser()->create();
    $requirement = JobBasedRequirement::factory()->create();

    DB::table('job_based_requirement_user')->insert([
        'user_id' => $user->getKey(),
        'job_based_requirement_id' => $requirement->getKey(),
        'is_active' => false,
        'valid_from' => today()->toDateString(),
        'calculated_at' => now()->subDay(),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $updatedRows = app(JobBasedRequirementEngineService::class)->promoteDueRequirements(CarbonImmutable::today());

    expect($updatedRows)->toBe(1);
    expect(DB::table('job_based_requirement_user')
        ->where('user_id', $user->getKey())
        ->where('job_based_requirement_id', $requirement->getKey())
        ->value('is_active'))->toBe(1);
});
