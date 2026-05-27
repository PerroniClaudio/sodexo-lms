<?php

use App\Enums\HierarchyLevel;
use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\NaceAteco;
use App\Models\RiskBasedRequirement;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeWorkerUser(array $attributes = []): User
{
    $user = User::query()->create(array_merge([
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

    $user->assignRole('user');

    return $user;
}

function prepareRiskContextForUser(User $user): array
{
    $naceAteco = NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 1,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $sector = JobSector::create([
        'name' => 'Sanita',
        'code' => 'SANITA',
        'description' => 'Settore sanitario',
    ]);
    $sector->naceAtecoCodes()->attach($naceAteco->code, [
        'inclusion_type' => InclusionType::DIVISION->value,
    ]);

    $title = JobTitle::create([
        'name' => 'Infermiere',
        'code' => 'INFERMIERE',
        'description' => 'Professionista sanitario',
    ]);
    $sector->jobTitles()->attach($title->getKey(), [
        'title_risk_level' => RiskLevel::HIGH->value,
    ]);

    $user->forceFill([
        'job_sector_id' => $sector->getKey(),
        'job_title_id' => $title->getKey(),
    ])->save();

    $validRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->create([
            'name' => 'Corso rischio alto',
        ]);

    $expiredRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->create([
            'name' => 'Aggiornamento periodico',
        ]);

    return [$validRequirement, $expiredRequirement];
}

beforeEach(function () {
    actingAsRole('superadmin');
});

it('lists user certificates with pagination search sorting and requirements', function () {
    $user = makeWorkerUser();
    [$validRequirement] = prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Primo soccorso',
            'issued_at' => '2026-01-10',
            'expires_at' => '2027-01-10',
        ]);
    $certificate->requirements()->attach([$validRequirement->getKey()]);

    UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Antincendio',
            'issued_at' => '2025-01-10',
        ]);

    $response = $this->getJson(route('admin.api.users.certificates.index', [
        'user' => $user,
        'search' => 'Primo',
        'sort' => 'name',
        'direction' => 'asc',
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Primo soccorso')
        ->assertJsonPath('data.0.requirements.0.name', 'Corso rischio alto');
});

it('stores a manual certificate and syncs linked requirements', function () {
    $user = makeWorkerUser();
    [$validRequirement, $expiredRequirement] = prepareRiskContextForUser($user);
    $course = Course::factory()->create(['title' => 'Corso interno']);

    $response = $this->postJson(route('admin.api.users.certificates.store', $user), [
        'name' => 'Attestato interno',
        'issued_at' => '2026-05-01',
        'expires_at' => '2027-05-01',
        'internal_course_id' => $course->getKey(),
        'requirements' => [$validRequirement->getKey(), $expiredRequirement->getKey()],
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Certificato registrato con successo.');

    $certificate = UserCertificate::query()->firstOrFail();

    expect($certificate->user_id)->toBe($user->getKey())
        ->and($certificate->is_internal)->toBeTrue()
        ->and($certificate->requirements()->pluck('risk_based_requirements.id')->all())
        ->toEqualCanonicalizing([$validRequirement->getKey(), $expiredRequirement->getKey()]);
});

it('updates a user certificate with the same form payload', function () {
    $user = makeWorkerUser();
    [$validRequirement, $expiredRequirement] = prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Attestato iniziale',
            'description' => 'Descrizione iniziale',
            'expires_at' => '2027-01-10',
        ]);
    $certificate->requirements()->attach($validRequirement->getKey());

    $response = $this->putJson(route('admin.api.users.certificates.update', [$user, $certificate]), [
        'name' => 'Attestato aggiornato',
        'description' => 'Nuova descrizione',
        'issued_at' => '2026-05-01',
        'expires_at' => '2028-05-01',
        'requirements' => [$expiredRequirement->getKey()],
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Certificato aggiornato con successo.');

    expect($certificate->fresh()->name)->toBe('Attestato aggiornato')
        ->and($certificate->fresh()->description)->toBe('Nuova descrizione')
        ->and($certificate->fresh()->requirements()->pluck('risk_based_requirements.id')->all())
        ->toEqualCanonicalizing([$expiredRequirement->getKey()]);
});

it('deletes a user certificate', function () {
    $user = makeWorkerUser();
    prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create();

    $response = $this->deleteJson(route('admin.api.users.certificates.destroy', [$user, $certificate]));

    $response->assertOk()
        ->assertJsonPath('message', 'Certificato eliminato con successo.');

    expect(UserCertificate::query()->whereKey($certificate->getKey())->exists())->toBeFalse();
});

it('returns the current risk summary via api', function () {
    $user = makeWorkerUser();
    [$validRequirement] = prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
    $certificate->requirements()->attach($validRequirement->getKey());

    $response = $this->getJson(route('admin.api.users.risk-summary', $user));

    $response->assertSuccessful()
        ->assertJsonPath('data.risk_label', 'Rischio Alto')
        ->assertJsonPath('data.requirements.0.requirement_name', 'Corso rischio alto');
});

it('updates the user through json and keeps the page refresh contract stable', function () {
    $user = makeWorkerUser([
        'email' => 'worker@example.test',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    prepareRiskContextForUser($user);

    $response = $this->putJson(route('admin.users.update', $user), [
        'account_type' => 'admin',
        'email' => 'worker.updated@example.test',
        'name' => 'Mario Updated',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Utente aggiornato con successo');

    expect($user->fresh()->email)->toBe('worker.updated@example.test')
        ->and($user->fresh()->getRoleNames()->all())->toBe(['admin']);
});

it('computes satisfied expired and missing requirements for compliance', function () {
    $user = makeWorkerUser();
    [$validRequirement, $expiredRequirement] = prepareRiskContextForUser($user);
    $missingRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->create([
            'name' => 'Formazione aggiuntiva',
        ]);

    $validCertificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Corso valido',
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    $validCertificate->requirements()->attach($validRequirement->getKey());

    $expiredCertificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Corso scaduto',
            'expires_at' => now()->subDay()->toDateString(),
        ]);
    $expiredCertificate->requirements()->attach($expiredRequirement->getKey());

    $compliance = $user->checkRequirementsCompliance()->keyBy('requirement_name');

    expect($missingRequirement->exists)->toBeTrue()
        ->and($compliance['Corso rischio alto']['status'])->toBe('satisfied')
        ->and($compliance['Aggiornamento periodico']['status'])->toBe('expired')
        ->and($compliance['Formazione aggiuntiva']['status'])->toBe('missing');
});
