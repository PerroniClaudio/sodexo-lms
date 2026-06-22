<?php

use App\Enums\RiskLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function makeStaffUser(array $attributes = []): User
{
    return User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ], $attributes));
}

function makeWorkerUser(array $attributes = []): User
{
    $jobUnit = JobUnit::query()->create([
        'name' => 'Sede test',
        'unit_code' => 'UT'.fake()->unique()->numerify('###'),
    ]);
    $jobRole = JobRole::factory()->create();
    $jobSector = JobSector::factory()->create();
    $primaryTask = JobTask::factory()->create(['name' => 'Mansione primaria']);
    $secondaryTask = JobTask::factory()->create(['name' => 'Mansione secondaria']);

    $user = makeStaffUser(array_merge([
        'job_unit_id' => $jobUnit->getKey(),
        'job_role_id' => $jobRole->getKey(),
        'job_sector_id' => $jobSector->getKey(),
        'job_task_id' => $primaryTask->getKey(),
        'employment_start_date' => '2026-01-01',
        'is_foreigner_or_immigrant' => false,
    ], $attributes));

    $user->assignRole('user');
    $user->jobTasks()->attach($primaryTask->getKey(), [
        'starts_at' => '2026-01-01',
        'ends_at' => null,
    ]);
    $user->jobTasks()->attach($secondaryTask->getKey(), [
        'starts_at' => '2026-03-01',
        'ends_at' => null,
    ]);

    return $user->fresh('jobTasks');
}

/**
 * @return array<string, mixed>
 */
function makeAdminUserStorePayload(array $overrides = []): array
{
    $jobUnit = JobUnit::query()->create([
        'name' => 'Sede creazione',
        'unit_code' => 'UC'.fake()->unique()->numerify('###'),
    ]);
    $jobRole = JobRole::factory()->create();
    $jobSector = JobSector::factory()->create();
    $jobTask = JobTask::factory()->create();

    return array_merge([
        'roles' => ['user'],
        'email' => fake()->unique()->safeEmail(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => '0',
        'employment_start_date' => '2026-01-01',
        'job_role_id' => $jobRole->getKey(),
        'job_sector_id' => $jobSector->getKey(),
        'job_unit_id' => $jobUnit->getKey(),
        'job_tasks' => [
            [
                'job_task_id' => $jobTask->getKey(),
                'starts_at' => '2026-01-01',
                'ends_at' => null,
            ],
        ],
    ], $overrides);
}

it('shows current spatie roles in the permissions section', function () {
    actingAsRole('superadmin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->get(route('admin.users.edit', $user).'?section=permissions');

    $response->assertOk();
    $response->assertSee('name="roles[]"', escape: false);
    $response->assertSee('value="teacher"', escape: false);
});

it('does not show an editable language verification field on admin user creation form', function () {
    actingAsRole('superadmin');

    $response = $this->get(route('admin.users.create'));

    $response->assertOk();
    $response->assertDontSee('name="needs_language_level_verification"', escape: false);
});

it('sets language verification automatically from immigrant field when immigrant functions are enabled', function () {
    config()->set('app.use_immigrant_functions', true);
    config()->set('app.default_check_language_knowledge', false);

    actingAsRole('superadmin');

    $payload = makeAdminUserStorePayload([
        'is_foreigner_or_immigrant' => '1',
        'needs_language_level_verification' => '0',
    ]);

    $response = $this->post(route('admin.users.store'), $payload);

    $response->assertRedirect(route('admin.users.index'));

    $createdUser = User::query()->where('email', $payload['email'])->firstOrFail();

    expect($createdUser->needs_language_level_verification)->toBeTrue();
});

it('uses default fallback for language verification when immigrant functions are disabled', function () {
    config()->set('app.use_immigrant_functions', false);
    config()->set('app.default_check_language_knowledge', true);

    actingAsRole('superadmin');

    $payload = makeAdminUserStorePayload([
        'is_foreigner_or_immigrant' => '0',
        'needs_language_level_verification' => '0',
    ]);

    $response = $this->post(route('admin.users.store'), $payload);

    $response->assertRedirect(route('admin.users.index'));

    $createdUser = User::query()->where('email', $payload['email'])->firstOrFail();

    expect($createdUser->needs_language_level_verification)->toBeTrue();
});

it('creates a worker user even when the email is not provided', function () {
    actingAsRole('superadmin');

    $payload = makeAdminUserStorePayload([
        'email' => '',
    ]);

    $response = $this->post(route('admin.users.store'), $payload);

    $response->assertRedirect(route('admin.users.index'));

    $createdUser = User::query()->where('fiscal_code', $payload['fiscal_code'])->firstOrFail();

    expect($createdUser->email)->toBeNull();
});

it('marks the job unit selector as required for worker users', function () {
    actingAsRole('superadmin');

    $user = makeWorkerUser([
        'email' => 'worker@example.test',
        'name' => 'Luca',
        'surname' => 'Verdi',
        'fiscal_code' => 'VRDLCU80A01H501Z',
    ]);

    $response = $this->get(route('admin.users.edit', $user).'?section=work');

    $response->assertOk();
    $response->assertSee('data-required="true"', escape: false);
    $response->assertSee('name="employment_start_date"', escape: false);
    $response->assertSee('name="job_tasks[0][job_task_id]"', escape: false);
    $response->assertSee('name="job_tasks[1][job_task_id]"', escape: false);
});

it('keeps the current role when updating other fields without changing roles', function () {
    actingAsRole('admin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->put(route('admin.users.update', $user), [
        'roles' => ['teacher'],
        'email' => 'teacher.updated@example.test',
        'name' => 'Mario Updated',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success', 'Utente aggiornato con successo');

    $user->refresh();

    expect($user->email)->toBe('teacher.updated@example.test');
    expect($user->name)->toBe('Mario Updated');
    expect($user->getRoleNames()->all())->toBe(['teacher']);
});

it('allows a superadmin to change roles from the permissions section', function () {
    actingAsRole('superadmin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->put(route('admin.users.permissions-section.update', $user), [
        'roles' => ['tutor'],
    ]);

    $response->assertRedirect(route('admin.users.edit', ['user' => $user, 'section' => 'permissions']));

    expect($user->fresh()->getRoleNames()->all())->toBe(['tutor']);
});

it('does not show editable role checkboxes to admin users', function () {
    actingAsRole('admin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->get(route('admin.users.edit', $user).'?section=permissions');

    $response->assertOk();
    $response->assertSee('name="roles[]" value="teacher"', escape: false);
    $response->assertSee('disabled', escape: false);
});

it('prevents an admin from changing the role via a crafted request', function () {
    actingAsRole('admin');

    $user = makeStaffUser([
        'email' => 'teacher@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    $user->assignRole('teacher');

    $response = $this->from(route('admin.users.edit', ['user' => $user, 'section' => 'permissions']))->put(route('admin.users.permissions-section.update', $user), [
        'roles' => ['tutor'],
    ]);

    $response->assertRedirect(route('admin.users.edit', ['user' => $user, 'section' => 'permissions']));
    $response->assertSessionHasErrors('roles.0');

    expect($user->fresh()->getRoleNames()->all())->toBe(['teacher']);
});

it('maps teacher selection to docente when the database only has the legacy role name', function () {
    actingAsRole('superadmin');

    Role::findByName('teacher')->delete();
    Role::findOrCreate('docente');

    $user = makeStaffUser([
        'email' => 'docente@example.test',
        'name' => 'Giulia',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCGLI80A01H501Z',
    ]);
    $user->assignRole('user');

    $response = $this->put(route('admin.users.update', $user), [
        'roles' => ['teacher'],
        'email' => 'docente@example.test',
        'name' => 'Giulia',
        'surname' => 'Bianchi',
        'fiscal_code' => 'BNCGLI80A01H501Z',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->getRoleNames()->all())->toBe(['docente']);
});

it('rejects worker updates without active job task coverage', function () {
    actingAsRole('superadmin');

    $user = makeWorkerUser([
        'email' => 'worker.validation@example.test',
        'name' => 'Paolo',
        'surname' => 'Neri',
        'fiscal_code' => 'NRIPLA80A01H501Z',
    ]);

    $jobTaskId = $user->jobTasks->first()->getKey();

    $response = $this->from(route('admin.users.edit', $user))->put(route('admin.users.update', $user), [
        'roles' => ['user'],
        'email' => 'worker.validation@example.test',
        'name' => 'Paolo',
        'surname' => 'Neri',
        'fiscal_code' => 'NRIPLA80A01H501Z',
        'is_foreigner_or_immigrant' => '0',
        'employment_start_date' => '2026-01-01',
        'job_role_id' => $user->job_role_id,
        'job_sector_id' => $user->job_sector_id,
        'job_unit_id' => $user->job_unit_id,
        'job_tasks' => [
            [
                'job_task_id' => $jobTaskId,
                'starts_at' => '2026-01-01',
                'ends_at' => '2026-02-01',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.users.edit', $user));
    $response->assertSessionHasErrors(['job_tasks', 'job_tasks.0.ends_at']);
});

it('warns when a worker update changes the current and future calculated risk', function () {
    Carbon::setTestNow('2026-01-15 12:00:00');

    actingAsRole('superadmin');

    $user = makeWorkerUser([
        'email' => 'worker.risk-change@example.test',
        'name' => 'Marta',
        'surname' => 'Blu',
        'fiscal_code' => 'BLUMRT80A01H501Z',
    ]);

    $user->jobSector()->update([
        'manual_risk_level' => RiskLevel::LOW->value,
    ]);

    $primaryTask = $user->jobTasks->firstWhere('name', 'Mansione primaria');
    $secondaryTask = $user->jobTasks->firstWhere('name', 'Mansione secondaria');

    $secondaryTask->jobSectors()->attach($user->job_sector_id, [
        'task_risk_level' => RiskLevel::HIGH->value,
        'sector_risk_override' => true,
    ]);

    $response = $this->put(route('admin.users.update', $user), [
        'roles' => ['user'],
        'email' => 'worker.risk-change@example.test',
        'name' => 'Marta',
        'surname' => 'Blu',
        'fiscal_code' => 'BLUMRT80A01H501Z',
        'is_foreigner_or_immigrant' => '0',
        'employment_start_date' => '2026-01-01',
        'job_role_id' => $user->job_role_id,
        'job_sector_id' => $user->job_sector_id,
        'job_unit_id' => $user->job_unit_id,
        'job_tasks' => [
            [
                'job_task_id' => $primaryTask->getKey(),
                'starts_at' => '2026-01-01',
                'ends_at' => null,
            ],
            [
                'job_task_id' => $secondaryTask->getKey(),
                'starts_at' => '2026-01-15',
                'ends_at' => null,
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('warning', function (string $warning): bool {
        return str_contains($warning, 'è cambiato da Rischio Basso a Rischio Alto')
            && str_contains($warning, 'modificano anche il rischio futuro');
    });

    Carbon::setTestNow();
});
