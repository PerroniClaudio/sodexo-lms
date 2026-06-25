<?php

use App\Http\Requests\ExportUserAccessRequest;
use App\Jobs\GenerateUserAccessExport;
use App\Models\JobSector;
use App\Models\User;
use App\Models\UserAccessExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('allows admins to access user access export page', function () {
    actingAsRole('admin');

    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.test',
    ]);

    $this->get(route('admin.user-accesses.index'))
        ->assertOk()
        ->assertSeeText('Accessi utente')
        ->assertSeeText('Esporta accessi piattaforma')
        ->assertSeeText($user->full_name);
});

it('does not allow regular users to access user access export page', function () {
    actingAsRole('user');

    $this->get(route('admin.user-accesses.index'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('validates scope specific fields and date ordering for user access export', function () {
    actingAsRole('admin');

    $this->from(route('admin.user-accesses.index'))
        ->post(route('admin.user-accesses.export'), [
            'scope_type' => ExportUserAccessRequest::SCOPE_JOB_DIMENSION,
            'date_from' => '2026-06-20',
            'date_to' => '2026-06-01',
        ])
        ->assertRedirect(route('admin.user-accesses.index'))
        ->assertSessionHasErrors(['job_dimension', 'job_dimension_id', 'date_to']);
});

it('exports user access log as xlsx for a specific user', function () {
    actingAsRole('admin');

    $targetUser = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
    ]);
    $otherUser = User::factory()->create([
        'name' => 'Luigi',
        'surname' => 'Verdi',
        'email' => 'luigi.verdi@example.test',
    ]);

    DB::table('users_access_log')->insert([
        [
            'user_id' => $targetUser->getKey(),
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Browser A',
            'logged_in_at' => '2026-06-10 08:30:00',
            'created_at' => '2026-06-10 08:30:00',
            'updated_at' => '2026-06-10 08:30:00',
        ],
        [
            'user_id' => $targetUser->getKey(),
            'ip_address' => '10.0.0.2',
            'user_agent' => 'Browser B',
            'logged_in_at' => '2026-06-12 10:15:00',
            'created_at' => '2026-06-12 10:15:00',
            'updated_at' => '2026-06-12 10:15:00',
        ],
        [
            'user_id' => $otherUser->getKey(),
            'ip_address' => '10.0.0.3',
            'user_agent' => 'Browser C',
            'logged_in_at' => '2026-06-11 11:00:00',
            'created_at' => '2026-06-11 11:00:00',
            'updated_at' => '2026-06-11 11:00:00',
        ],
    ]);

    $response = $this->post(route('admin.user-accesses.export'), [
        'scope_type' => ExportUserAccessRequest::SCOPE_USER,
        'user_id' => $targetUser->getKey(),
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
    ]);

    $response->assertOk()->assertDownload('accessi-utenti-utente-20260601-20260630.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'user-access-export-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);

    expect($spreadsheet->getSheet(0)->getTitle())->toBe('Accessi utente')
        ->and($spreadsheet->getSheet(0)->getCell('A1')->getValue())->toBe('Log ID')
        ->and($spreadsheet->getSheet(0)->getCell('C2')->getValue())->toBe('Mario')
        ->and($spreadsheet->getSheet(0)->getCell('D3')->getValue())->toBe('Rossi')
        ->and($spreadsheet->getSheet(0)->getCell('F2')->getValue())->toBe('10.0.0.1')
        ->and($spreadsheet->getSheet(0)->getCell('G3')->getValue())->toBe('Browser B')
        ->and($spreadsheet->getSheet(0)->getCell('E4')->getValue())->toBeNull();

    @unlink($temporaryFile);
});

it('queues user access log export for a user group', function () {
    Queue::fake();
    actingAsRole('admin');

    $selectedSector = JobSector::factory()->create(['name' => 'Sanita']);
    $otherSector = JobSector::factory()->create(['name' => 'Logistica']);

    $includedUser = User::factory()->create([
        'name' => 'Anna',
        'surname' => 'Bianchi',
        'email' => 'anna.bianchi@example.test',
        'job_sector_id' => $selectedSector->getKey(),
    ]);
    $excludedUser = User::factory()->create([
        'name' => 'Paolo',
        'surname' => 'Neri',
        'email' => 'paolo.neri@example.test',
        'job_sector_id' => $otherSector->getKey(),
    ]);

    DB::table('users_access_log')->insert([
        [
            'user_id' => $includedUser->getKey(),
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Sector Browser',
            'logged_in_at' => '2026-06-08 09:00:00',
            'created_at' => '2026-06-08 09:00:00',
            'updated_at' => '2026-06-08 09:00:00',
        ],
        [
            'user_id' => $excludedUser->getKey(),
            'ip_address' => '192.168.1.2',
            'user_agent' => 'Other Browser',
            'logged_in_at' => '2026-06-08 09:15:00',
            'created_at' => '2026-06-08 09:15:00',
            'updated_at' => '2026-06-08 09:15:00',
        ],
    ]);

    $this->post(route('admin.user-accesses.export'), [
        'scope_type' => ExportUserAccessRequest::SCOPE_JOB_DIMENSION,
        'job_dimension' => 'job_sector',
        'job_dimension_id' => $selectedSector->getKey(),
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
    ])
        ->assertRedirect(route('admin.user-accesses.index'))
        ->assertSessionHas('status', 'Richiesta export accodata con successo.');

    $userAccessExport = UserAccessExport::query()->sole();

    expect($userAccessExport->status)->toBe(UserAccessExport::STATUS_PENDING)
        ->and($userAccessExport->job_dimension)->toBe('job_sector')
        ->and($userAccessExport->job_dimension_id)->toBe($selectedSector->getKey());

    Queue::assertPushed(GenerateUserAccessExport::class, function (GenerateUserAccessExport $job) use ($userAccessExport): bool {
        return $job->userAccessExport->is($userAccessExport);
    });
});

it('returns status payload for queued user group export', function () {
    actingAsRole('admin');

    $userAccessExport = UserAccessExport::query()->create([
        'requested_by' => auth()->id(),
        'scope_type' => ExportUserAccessRequest::SCOPE_JOB_DIMENSION,
        'job_dimension' => 'job_sector',
        'job_dimension_id' => JobSector::factory()->create()->getKey(),
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'status' => UserAccessExport::STATUS_PROCESSING,
        'output_disk' => 's3',
    ]);

    $this->get(route('admin.user-accesses.show', $userAccessExport))
        ->assertOk()
        ->assertJson([
            'id' => $userAccessExport->getKey(),
            'status' => UserAccessExport::STATUS_PROCESSING,
            'status_label' => 'In lavorazione',
            'is_terminal' => false,
            'download_url' => null,
        ]);
});

it('allows admins to download completed queued user group export files', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $userAccessExport = UserAccessExport::query()->create([
        'requested_by' => auth()->id(),
        'scope_type' => ExportUserAccessRequest::SCOPE_JOB_DIMENSION,
        'job_dimension' => 'job_sector',
        'job_dimension_id' => JobSector::factory()->create()->getKey(),
        'date_from' => '2026-06-01',
        'date_to' => '2026-06-30',
        'status' => UserAccessExport::STATUS_COMPLETED,
        'output_disk' => 's3',
        'output_path' => 'user-access-exports/1/report.xlsx',
        'completed_at' => now(),
    ]);

    Storage::disk('s3')->put($userAccessExport->output_path, 'PK-xlsx-content');

    $response = $this->get(route('admin.user-accesses.download', $userAccessExport))
        ->assertDownload('report.xlsx');

    expect($response->streamedContent())->toBe('PK-xlsx-content');
});
