<?php

use App\Enums\HierarchyLevel;
use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DocumentType;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\NaceAteco;
use App\Models\RiskBasedRequirement;
use App\Models\User;
use App\Models\UserCertificate;
use App\Models\UserCertificateFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeCertificateWorkerUser(array $attributes = []): User
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
        'employment_start_date' => now()->subYear()->toDateString(),
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

    $role = JobRole::create([
        'name' => 'Preposto',
        'description' => 'Ruolo anagrafico',
    ]);
    $task = JobTask::create([
        'name' => 'Infermiere',
        'description' => 'Professionista sanitario',
    ]);
    $task->jobSectors()->attach($sector->getKey(), [
        'task_risk_level' => RiskLevel::HIGH->value,
    ]);

    $user->forceFill([
        'job_sector_id' => $sector->getKey(),
        'job_role_id' => $role->getKey(),
        'job_task_id' => $task->getKey(),
    ])->save();
    $user->jobTasks()->attach($task->getKey(), [
        'starts_at' => $user->employment_start_date?->toDateString() ?? now()->toDateString(),
        'ends_at' => $user->employment_end_date?->toDateString(),
    ]);

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
    $this->adminUser = actingAsRole('superadmin');
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
});

it('lists user certificates with pagination search sorting and risk-based requirements', function () {
    $user = makeCertificateWorkerUser();
    [$validRequirement] = prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Primo soccorso',
            'issued_at' => '2026-01-10',
            'expires_at' => '2027-01-10',
        ]);
    $certificate->riskBasedRequirements()->attach([$validRequirement->getKey()]);

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
        ->assertJsonPath('data.0.risk_based_requirements.0.name', 'Corso rischio alto');
});

it('stores a manual certificate and syncs linked risk-based requirements', function () {
    $user = makeCertificateWorkerUser();
    [$validRequirement, $expiredRequirement] = prepareRiskContextForUser($user);
    $course = Course::factory()->create(['title' => 'Corso interno']);
    $documentType = DocumentType::factory()->create(['name' => 'Pronto soccorso']);
    $uploadedFile = UploadedFile::fake()->create('attestato.pdf', 120, 'application/pdf');

    $response = $this->post(route('admin.api.users.certificates.store', $user), [
        'name' => 'Attestato interno',
        'document_type_id' => $documentType->getKey(),
        'issued_at' => '2026-05-01',
        'expires_at' => '2027-05-01',
        'internal_course_id' => $course->getKey(),
        'risk_based_requirement_ids' => [$validRequirement->getKey(), $expiredRequirement->getKey()],
        'files' => [$uploadedFile],
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Certificato registrato con successo.');

    $certificate = UserCertificate::query()->firstOrFail();
    $storedFile = $certificate->files()->sole();

    expect($certificate->user_id)->toBe($user->getKey())
        ->and($certificate->is_internal)->toBeTrue()
        ->and($certificate->document_type_id)->toBe($documentType->getKey())
        ->and($certificate->riskBasedRequirements()->pluck('risk_based_requirements.id')->all())
        ->toEqualCanonicalizing([$validRequirement->getKey(), $expiredRequirement->getKey()])
        ->and($storedFile->original_name)->toBe('attestato.pdf');

    Storage::disk('s3')->assertExists($storedFile->path);
});

it('updates a user certificate and soft deletes previously active files when a new one is uploaded', function () {
    $user = makeCertificateWorkerUser();
    [$validRequirement, $expiredRequirement] = prepareRiskContextForUser($user);
    $documentType = DocumentType::factory()->create(['name' => 'Parte generale']);
    $updatedDocumentType = DocumentType::factory()->create(['name' => 'Privacy']);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Attestato iniziale',
            'description' => 'Descrizione iniziale',
            'document_type_id' => $documentType->getKey(),
            'expires_at' => '2027-01-10',
        ]);
    $certificate->riskBasedRequirements()->attach($validRequirement->getKey());
    $existingFile = UserCertificateFile::factory()->create([
        'user_certificate_id' => $certificate->getKey(),
        'uploaded_by' => $this->adminUser->getKey(),
        'path' => 'users/'.$user->getKey().'/certificates/file/precedente.pdf',
        'original_name' => 'precedente.pdf',
    ]);
    Storage::disk('s3')->put($existingFile->path, 'old file');
    $replacementFile = UploadedFile::fake()->create('nuovo-attestato.pdf', 220, 'application/pdf');

    $response = $this->put(route('admin.api.users.certificates.update', [$user, $certificate]), [
        'name' => 'Attestato aggiornato',
        'description' => 'Nuova descrizione',
        'document_type_id' => $updatedDocumentType->getKey(),
        'issued_at' => '2026-05-01',
        'expires_at' => '2028-05-01',
        'risk_based_requirement_ids' => [$expiredRequirement->getKey()],
        'files' => [$replacementFile],
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Certificato aggiornato con successo.');

    $certificate->refresh();

    expect($certificate->name)->toBe('Attestato aggiornato')
        ->and($certificate->description)->toBe('Nuova descrizione')
        ->and($certificate->document_type_id)->toBe($updatedDocumentType->getKey())
        ->and($certificate->riskBasedRequirements()->pluck('risk_based_requirements.id')->all())
        ->toEqualCanonicalizing([$expiredRequirement->getKey()])
        ->and($certificate->files()->count())->toBe(1)
        ->and($certificate->allFiles()->count())->toBe(2)
        ->and($certificate->allFiles()->whereKey($existingFile->getKey())->first()?->trashed())->toBeTrue();

    Storage::disk('s3')->assertExists($existingFile->path);
    Storage::disk('s3')->assertExists($certificate->files()->sole()->path);
});

it('deletes a user certificate', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create();

    $response = $this->deleteJson(route('admin.api.users.certificates.destroy', [$user, $certificate]));

    $response->assertOk()
        ->assertJsonPath('message', 'Certificato eliminato con successo.');

    expect(UserCertificate::query()->whereKey($certificate->getKey())->exists())->toBeFalse();
});

it('lists certificate files and allows soft deleting them via api', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()->for($user)->create();
    $activeFile = UserCertificateFile::factory()->create([
        'user_certificate_id' => $certificate->getKey(),
        'uploaded_by' => $this->adminUser->getKey(),
        'path' => 'users/'.$user->getKey().'/certificates/file/attivo.pdf',
        'original_name' => 'attivo.pdf',
    ]);
    $deletedFile = UserCertificateFile::factory()->create([
        'user_certificate_id' => $certificate->getKey(),
        'uploaded_by' => $this->adminUser->getKey(),
        'path' => 'users/'.$user->getKey().'/certificates/file/eliminato.pdf',
        'original_name' => 'eliminato.pdf',
        'deleted_at' => now(),
    ]);
    Storage::disk('s3')->put($activeFile->path, 'active file');
    Storage::disk('s3')->put($deletedFile->path, 'deleted file');

    $response = $this->getJson(route('admin.api.users.certificates.files.index', [
        'user' => $user,
        'userCertificate' => $certificate,
        'show_deleted_files' => 1,
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('meta.active_files_count', 1)
        ->assertJsonPath('meta.total_files_count', 2)
        ->assertJsonCount(2, 'data');

    $deleteResponse = $this->deleteJson(route('admin.api.users.certificates.files.delete', [
        'user' => $user,
        'userCertificate' => $certificate,
        'userCertificateFile' => $activeFile,
    ]));

    $deleteResponse->assertOk()
        ->assertJsonPath('message', 'File certificato eliminato con successo.');

    expect($activeFile->fresh()->trashed())->toBeTrue();
});

it('downloads certificate files for admins', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()->for($user)->create();
    $file = UserCertificateFile::factory()->create([
        'user_certificate_id' => $certificate->getKey(),
        'uploaded_by' => $this->adminUser->getKey(),
        'path' => 'users/'.$user->getKey().'/certificates/file/file.pdf',
        'original_name' => 'file.pdf',
    ]);
    Storage::disk('s3')->put($file->path, 'pdf-content');

    $this->get(route('admin.api.users.certificates.files.download', [
        'user' => $user,
        'userCertificate' => $certificate,
        'userCertificateFile' => $file,
    ]))->assertOk();
});

it('returns the current risk summary via api', function () {
    $user = makeCertificateWorkerUser();
    [$validRequirement] = prepareRiskContextForUser($user);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($validRequirement->getKey());

    $response = $this->getJson(route('admin.api.users.risk-summary', $user));

    $response->assertSuccessful()
        ->assertJsonPath('data.risk_label', 'Rischio Alto')
        ->assertJsonFragment(['risk_based_requirement_name' => 'Corso rischio alto']);
});

it('updates the user through json and keeps the page refresh contract stable', function () {
    $user = makeCertificateWorkerUser([
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

it('computes satisfied expired and missing risk-based requirements for compliance', function () {
    $user = makeCertificateWorkerUser();
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
    $validCertificate->riskBasedRequirements()->attach($validRequirement->getKey());

    $expiredCertificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Corso scaduto',
            'expires_at' => now()->subDay()->toDateString(),
        ]);
    $expiredCertificate->riskBasedRequirements()->attach($expiredRequirement->getKey());

    $riskBasedRequirementsCompliance = $user->checkRiskBasedRequirementsCompliance()->keyBy('risk_based_requirement_name');

    expect($missingRequirement->exists)->toBeTrue()
        ->and($riskBasedRequirementsCompliance['Corso rischio alto']['status'])->toBe('satisfied')
        ->and($riskBasedRequirementsCompliance['Aggiornamento periodico']['status'])->toBe('expired')
        ->and($riskBasedRequirementsCompliance['Aggiornamento periodico']['required_course_validity_type'])->toBe('refresh')
        ->and($riskBasedRequirementsCompliance['Aggiornamento periodico']['required_course_validity_type_label'])->toBe('Aggiornamento')
        ->and($riskBasedRequirementsCompliance['Formazione aggiuntiva']['status'])->toBe('missing')
        ->and($riskBasedRequirementsCompliance['Formazione aggiuntiva']['required_course_validity_type'])->toBe('first_achievement')
        ->and($riskBasedRequirementsCompliance['Formazione aggiuntiva']['required_course_validity_type_label'])->toBe('Primo conseguimento');
});

it('treats a higher-risk valid certificate as satisfying a lower-risk requirement in the same progression group', function () {
    $user = makeCertificateWorkerUser();
    $naceAteco = NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 1,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);
    $sector = JobSector::create([
        'name' => 'Sanita media',
        'code' => 'SAN_MEDIA',
        'description' => 'Settore medio',
    ]);
    $sector->naceAtecoCodes()->attach($naceAteco->code, [
        'inclusion_type' => InclusionType::DIVISION->value,
    ]);
    $role = JobRole::create([
        'name' => 'Operatore',
        'description' => 'Ruolo anagrafico',
    ]);
    $task = JobTask::create([
        'name' => 'Assistente',
        'description' => 'Mansione media',
    ]);
    $task->jobSectors()->attach($sector->getKey(), [
        'task_risk_level' => RiskLevel::MEDIUM->value,
    ]);
    $user->forceFill([
        'job_sector_id' => $sector->getKey(),
        'job_role_id' => $role->getKey(),
        'job_task_id' => $task->getKey(),
    ])->save();
    $user->jobTasks()->attach($task->getKey(), [
        'starts_at' => $user->employment_start_date?->toDateString() ?? now()->toDateString(),
    ]);

    $mediumRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::MEDIUM)
        ->progressionGroup('specific-worker-training')
        ->create(['name' => 'Corso rischio medio']);
    $highRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->create(['name' => 'Corso rischio alto']);

    $certificate = UserCertificate::factory()
        ->for($user)
        ->create([
            'name' => 'Corso rischio alto valido',
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    $certificate->riskBasedRequirements()->attach($highRequirement->getKey());

    $riskBasedRequirementsCompliance = $user->checkRiskBasedRequirementsCompliance()->keyBy('risk_based_requirement_name');

    expect($riskBasedRequirementsCompliance['Corso rischio medio']['status'])->toBe('satisfied')
        ->and($riskBasedRequirementsCompliance['Corso rischio medio']['covered_by_higher_risk_certificate'])->toBeTrue();
});

it('shows the manual course selection page', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $response = $this->get(route('admin.users.risk-course-selection', $user));

    $response->assertOk()
        ->assertSeeText('Selezione manuale corso')
        ->assertSeeText($user->full_name);
});

it('shows future risk transitions on the manual course selection page', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $currentTask = $user->jobTasks()->firstOrFail();
    $sector = $user->jobSector()->firstOrFail();
    $transitionStartDate = now()->addMonth()->startOfDay();

    $futureTask = JobTask::factory()->create();
    $futureTask->jobSectors()->attach($sector->getKey(), [
        'task_risk_level' => RiskLevel::MEDIUM->value,
        'sector_risk_override' => true,
    ]);
    $user->jobTasks()->updateExistingPivot($currentTask->getKey(), [
        'starts_at' => $user->employment_start_date?->toDateString() ?? now()->subYear()->toDateString(),
        'ends_at' => $transitionStartDate->copy()->subDay()->toDateString(),
    ]);
    $user->jobTasks()->attach($futureTask->getKey(), [
        'starts_at' => $transitionStartDate->toDateString(),
        'ends_at' => null,
    ]);

    $response = $this->get(route('admin.users.risk-course-selection', $user));

    $response->assertOk()
        ->assertSeeText('Variazioni di rischio future')
        ->assertSeeText($transitionStartDate->format('d/m/Y'))
        ->assertSeeText(RiskLevel::MEDIUM->label());
});

it('shows whether a missing requirement already has an associated enrolled course', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $missingRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->create([
            'name' => 'Formazione obbligatoria mancante',
        ]);

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza associato',
        'status' => 'published',
    ]);

    $course->riskBasedRequirements()->attach($missingRequirement->getKey(), [
        'course_validity_types' => json_encode(['first_achievement']),
    ]);

    CourseEnrollment::query()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_ASSIGNED,
        'assigned_at' => now(),
    ]);

    $response = $this->get(route('admin.users.risk-course-selection', $user));

    $response->assertOk()
        ->assertSeeText('Corso già associato: Corso sicurezza associato');
});

it('marks recommended courses as already enrolled in the api response', function () {
    $user = makeCertificateWorkerUser();
    prepareRiskContextForUser($user);

    $missingRequirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->create([
            'name' => 'Corso da assegnare',
        ]);

    $course = Course::factory()->create([
        'title' => 'Corso con iscrizione attiva',
        'status' => 'published',
    ]);

    $course->riskBasedRequirements()->attach($missingRequirement->getKey(), [
        'course_validity_types' => json_encode(['first_achievement']),
    ]);

    CourseEnrollment::query()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_ASSIGNED,
        'assigned_at' => now(),
    ]);

    $response = $this->getJson(route('admin.api.users.recommended-courses', $user));

    $response->assertOk()
        ->assertJsonPath('data.0.title', 'Corso con iscrizione attiva')
        ->assertJsonPath('data.0.is_enrolled', true);
});
