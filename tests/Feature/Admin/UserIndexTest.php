<?php

use App\Enums\RiskLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createWorkerWithRisk(RiskLevel $riskLevel, array $attributes = []): User
{
    $jobUnit = JobUnit::query()->create([
        'name' => 'Sede '.fake()->unique()->city(),
        'unit_code' => 'UT'.fake()->unique()->numerify('###'),
    ]);
    $jobRole = JobRole::factory()->create();
    $jobSector = JobSector::factory()->create([
        'manual_risk_level' => $riskLevel,
    ]);
    $jobTask = JobTask::factory()->create();

    $user = User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'job_unit_id' => $jobUnit->getKey(),
        'job_role_id' => $jobRole->getKey(),
        'job_sector_id' => $jobSector->getKey(),
        'job_task_id' => $jobTask->getKey(),
        'employment_start_date' => now()->subMonth()->toDateString(),
        'is_foreigner_or_immigrant' => false,
    ], $attributes));

    $user->assignRole('user');
    $user->jobTasks()->attach($jobTask->getKey(), [
        'starts_at' => now()->subMonth()->toDateString(),
        'ends_at' => null,
    ]);

    return $user->fresh(['jobSector', 'jobTasks', 'roles']);
}

it('shows user risk overview above admin users table', function () {
    actingAsRole('admin');

    createWorkerWithRisk(RiskLevel::LOW, [
        'name' => 'Luca',
        'surname' => 'Basso',
        'email' => 'luca.basso@example.test',
    ]);
    createWorkerWithRisk(RiskLevel::MEDIUM, [
        'name' => 'Marta',
        'surname' => 'Media',
        'email' => 'marta.media@example.test',
    ]);
    createWorkerWithRisk(RiskLevel::HIGH, [
        'name' => 'Paolo',
        'surname' => 'Alto',
        'email' => 'paolo.alto@example.test',
    ]);
    $staffUser = User::query()->create([
        'name' => 'Admin',
        'surname' => 'No Risk',
        'email' => 'admin.no-risk@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ]);
    $staffUser->assignRole('teacher');

    $response = $this->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSeeText('Distribuzione utenti per rischio');
    $response->assertSeeText('Totale utenti');
    $response->assertSeeText('Rischio Basso');
    $response->assertSeeText('Rischio Medio');
    $response->assertSeeText('Rischio Alto');
    $response->assertDontSeeText('Senza rischio calcolabile');
    $response->assertViewHas('userRiskOverview', fn (array $overview): bool => $overview['total_users'] === 3
        && $overview['classified_users'] === 3
        && $overview['unclassified_users'] === 2
        && collect($overview['risk_counts'])->pluck('count', 'level')->all() === [
            RiskLevel::LOW->value => 1,
            RiskLevel::MEDIUM->value => 1,
            RiskLevel::HIGH->value => 1,
        ]);
});
