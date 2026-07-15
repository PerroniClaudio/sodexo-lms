<?php

use App\Jobs\ImportUsersJob;
use App\Models\Importazione;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\LanguageLevel;
use App\Models\User;
use App\Services\UserImportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('queues a user import from excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.users.store'), [
        'file' => userImportFile([
            ['Admin', 'Mario', 'Rossi', 'RSSMRA80A01H501Z'],
        ], [
            'Tipo di account',
            'Nome',
            'Cognome',
            'Codice fiscale',
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.users'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_USERS)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('utenti.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportUsersJob::class, fn (ImportUsersJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('queues a quick user import from excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.users.quick.store'), [
        'file' => userImportFile([
            ['RSSMRA80A01H501Z', 'Mario', 'Rossi', 'User', 'M', 'IT', 'Scuole', 'Operatore', 'Mansione uno', 'Unità uno', 'NO', '01/01/2024'],
        ], [
            'Codice fiscale',
            'Nome',
            'Cognome',
            'Tipo di account',
            'Genere',
            'Nazionalità',
            'Settore',
            'Ruolo',
            'Mansione (nome; separa con ;)',
            'Unità lavorativa (nome)',
            'Straniero',
            'Data di assunzione',
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.users.quick'))
        ->assertSessionHas('status');

    expect(Importazione::query()->sole()->import_type)->toBe(Importazione::TYPE_USERS_QUICK);
});

it('requires profile completion when a quick import updates a worker', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Notification::fake();

    test()->seed(RoleAndPermissionSeeder::class);

    $worker = User::factory()->asUser()->create([
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'account_state' => 'active',
        'profile_completed_at' => now(),
    ]);
    JobRole::factory()->create(['name' => 'Operatore']);
    JobSector::factory()->create(['name' => 'Scuole']);
    JobTask::factory()->create(['name' => 'Mansione uno', 'code' => 'TASK-001']);
    JobUnit::factory()->create(['name' => 'Unità uno', 'unit_code' => 'UNIT-001']);

    Storage::disk('s3')->put('imports/users/quick-users.xlsx', file_get_contents(
        userImportFile([
            ['RSSMRA80A01H501Z', 'Mario', 'Rossi', 'User', 'M', 'IT', 'Scuole', 'Operatore', 'Mansione uno', 'Unità uno', 'NO', '01/01/2024'],
        ], [
            'Codice fiscale',
            'Nome',
            'Cognome',
            'Tipo di account',
            'Genere',
            'Nazionalità',
            'Settore',
            'Ruolo',
            'Mansione (nome; separa con ;)',
            'Unità lavorativa (nome)',
            'Straniero',
            'Data di assunzione',
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USERS_QUICK,
        'file_path' => 'imports/users/quick-users.xlsx',
        'original_file_name' => 'utenti-rapido.xlsx',
    ]);

    app(ImportUsersJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(UserImportService::class));

    expect($worker->fresh()->account_state->value)->toBe('onboarding')
        ->and($worker->fresh()->profile_completed_at)->toBeNull();
});

it('downloads user import template', function () {
    actingAsRole('admin');

    JobCategory::factory()->create(['name' => 'Impiegati']);
    JobLevel::factory()->create(['name' => 'Quadro']);
    JobRole::factory()->create(['name' => 'Operatore']);
    JobSector::factory()->create(['name' => 'Scuole']);
    JobTask::factory()->create(['name' => 'Mansione uno', 'code' => 'TASK-001']);
    JobTask::factory()->create(['name' => 'Mansione due', 'code' => 'TASK-002']);
    JobUnit::factory()->create(['name' => 'Unità uno', 'unit_code' => 'UNIT-001']);
    $firstLanguageLevel = LanguageLevel::query()->orderBy('sort_order')->orderBy('name')->value('name');
    $languageLevelRows = LanguageLevel::query()->count();

    $response = $this->get(route('admin.imports.users.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-utenti.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Import utenti');
    $lookupSheet = $spreadsheet->getSheetByName('Valori disponibili');

    expect($importSheet?->getCell('Q2')->getValue())->toBe('Impiegati')
        ->and($importSheet?->getCell('R2')->getValue())->toBe('Quadro')
        ->and($importSheet?->getCell('S2')->getValue())->toBe('Operatore')
        ->and($importSheet?->getCell('T2')->getValue())->toBe('Mansione due;Mansione uno')
        ->and($importSheet?->getCell('U2')->getValue())->toBe('Unità uno')
        ->and($lookupSheet?->getCell('A2')->getValue())->toBe('IT')
        ->and($lookupSheet?->getCell('B2')->getValue())->toBe('M')
        ->and($lookupSheet?->getCell('C2')->getValue())->toBe('Scuole')
        ->and($lookupSheet?->getCell('D2')->getValue())->toBe('Impiegati')
        ->and($lookupSheet?->getCell('E2')->getValue())->toBe('Quadro')
        ->and($lookupSheet?->getCell('F2')->getValue())->toBe('Operatore')
        ->and($lookupSheet?->getCell('G2')->getValue())->toBe('Mansione due')
        ->and($lookupSheet?->getCell('H2')->getValue())->toBe('Unità uno')
        ->and($lookupSheet?->getCell('I2')->getValue())->toBe('SI')
        ->and($lookupSheet?->getCell('J2')->getValue())->toBe($firstLanguageLevel)
        ->and($importSheet?->getCell('H2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$A\$2:\$A\$2")
        ->and($importSheet?->getCell('O2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$B\$2:\$B\$3")
        ->and($importSheet?->getCell('P2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$C\$2:\$C\$2")
        ->and($importSheet?->getCell('Q2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$D\$2:\$D\$2")
        ->and($importSheet?->getCell('R2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$E\$2:\$E\$2")
        ->and($importSheet?->getCell('S2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$F\$2:\$F\$2")
        ->and($importSheet?->getCell('U2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$H\$2:\$H\$2")
        ->and($importSheet?->getCell('V2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$I\$2:\$I\$3")
        ->and($importSheet?->getCell('Y2')->getDataValidation()->getFormula1())->toBe("'Valori disponibili'!\$J\$2:\$J\$".($languageLevelRows + 1));

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('downloads quick user import template', function () {
    actingAsRole('admin');

    JobRole::factory()->create(['name' => 'Operatore']);
    JobSector::factory()->create(['name' => 'Scuole']);
    JobTask::factory()->create(['name' => 'Mansione uno', 'code' => 'TASK-001']);
    JobUnit::factory()->create(['name' => 'Unità uno', 'unit_code' => 'UNIT-001']);

    $response = $this->get(route('admin.imports.users.quick.template'));

    $response->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=template-import-utenti-rapido.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'quick-template-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Import utenti rapido');
    $lookupSheet = $spreadsheet->getSheetByName('Valori disponibili');

    expect($importSheet?->getCell('A1')->getValue())->toBe('Codice fiscale')
        ->and($importSheet?->getCell('L1')->getValue())->toBe('Data di assunzione')
        ->and($lookupSheet?->getCell('D1')->getValue())->toBe('Mansione (nome)')
        ->and($lookupSheet?->getHighestRow())->toBeGreaterThan(1)
        ->and($importSheet?->getCell('K2')->getDataValidation()->getFormula1())->toContain("'Valori disponibili'!\$F\$2:\$F\$");

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('returns user import status card payload', function () {
    actingAsRole('admin');

    Importazione::query()->create([
        'import_type' => Importazione::TYPE_USERS,
        'created_by' => auth()->id(),
        'status' => Importazione::STATUS_PROGRESS,
        'file_path' => 'imports/users/progress.xlsx',
        'original_file_name' => 'progress-originale.xlsx',
    ]);

    $this->get(route('admin.imports.users.status-card'))
        ->assertOk()
        ->assertSeeText('Import utenti completo recenti')
        ->assertSeeText('In lavorazione')
        ->assertSeeText('progress-originale.xlsx');
});

it('imports workers and staff from excel', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Notification::fake();

    test()->seed(RoleAndPermissionSeeder::class);

    LanguageLevel::query()->firstOrCreate(['name' => 'a2'], [
        'name' => 'a2',
        'sort_order' => 1,
        'is_default' => true,
    ]);
    LanguageLevel::query()->firstOrCreate(['name' => 'b2'], [
        'name' => 'b2',
        'sort_order' => 2,
        'is_default' => false,
    ]);

    $jobCategory = JobCategory::factory()->create(['name' => 'Impiegati']);
    $jobLevel = JobLevel::factory()->create(['name' => 'Quadro']);
    $jobRole = JobRole::factory()->create(['name' => 'Operatore']);
    $jobSector = JobSector::factory()->create(['name' => 'Scuole']);
    $jobTask = JobTask::factory()->create(['name' => 'Mansione uno', 'code' => 'TASK-001']);
    $secondJobTask = JobTask::factory()->create(['name' => 'Mansione due', 'code' => 'TASK-002']);
    $jobUnit = JobUnit::factory()->create(['name' => 'Unità uno', 'unit_code' => 'UNIT-001']);

    $countryId = DB::table('world_countries')->where('code', 'it')->value('id');
    $regionId = DB::table('world_divisions')->where('name', 'Lazio')->value('id');
    $provinceId = DB::table('provinces')->where('code', 'RM')->value('id');

    Storage::disk('s3')->put('imports/users/users.xlsx', file_get_contents(
        userImportFile([
            [
                'mario.rossi@example.test',
                'User;Docente',
                'Mario',
                'Rossi',
                '+39',
                '3331234567',
                'RSSMRA80A01H501Z',
                'IT',
                'Lazio',
                'RM',
                'Via Roma 1',
                '00100',
                '10/02/1980',
                'Roma',
                'M',
                'Scuole',
                'Impiegati',
                'Quadro',
                'Operatore',
                'Mansione uno;Mansione due',
                'Unità uno',
                'NO',
                '01/01/2024',
                null,
                'A2',
            ],
            [
                'anna.bianchi@example.test',
                'Admin',
                'Anna',
                'Bianchi',
                null,
                null,
                'BNCNNA80A01H501Z',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USERS,
        'file_path' => 'imports/users/users.xlsx',
        'original_file_name' => 'utenti-reali.xlsx',
    ]);

    app(ImportUsersJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(UserImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($importazione->fresh()->error_message)->toBeNull();

    $worker = User::query()->where('fiscal_code', 'RSSMRA80A01H501Z')->firstOrFail();
    $admin = User::query()->where('fiscal_code', 'BNCNNA80A01H501Z')->firstOrFail();

    expect($worker->email)->toBe('mario.rossi@example.test')
        ->and($worker->job_sector_id)->toBe($jobSector->getKey())
        ->and($worker->job_category_id)->toBe($jobCategory->getKey())
        ->and($worker->job_level_id)->toBe($jobLevel->getKey())
        ->and($worker->job_role_id)->toBe($jobRole->getKey())
        ->and($worker->job_unit_id)->toBe($jobUnit->getKey())
        ->and($worker->home_country_id)->toBe($countryId)
        ->and($worker->home_region_id)->toBe($regionId)
        ->and($worker->home_province_id)->toBe($provinceId)
        ->and($worker->is_foreigner_or_immigrant)->toBeFalse()
        ->and($worker->hasRole('user'))->toBeTrue()
        ->and($worker->hasRole('teacher'))->toBeTrue()
        ->and($worker->jobTasks()->count())->toBe(2)
        ->and($worker->jobTasks()->pluck('job_tasks.id')->sort()->values()->all())->toBe([
            $jobTask->getKey(),
            $secondJobTask->getKey(),
        ])
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->account_state->value)->toBe('active');
});

it('fails import when worker required data is missing', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Notification::fake();

    test()->seed(RoleAndPermissionSeeder::class);

    LanguageLevel::query()->firstOrCreate(['name' => 'a2'], [
        'name' => 'a2',
        'sort_order' => 1,
        'is_default' => true,
    ]);

    Storage::disk('s3')->put('imports/users/invalid-users.xlsx', file_get_contents(
        userImportFile([
            ['User', 'Mario', 'Rossi', 'RSSMRA80A01H501Z'],
        ], [
            'Tipo di account',
            'Nome',
            'Cognome',
            'Codice fiscale',
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USERS,
        'file_path' => 'imports/users/invalid-users.xlsx',
        'original_file_name' => 'utenti-invalidi.xlsx',
    ]);

    app(ImportUsersJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(UserImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('Riga 2')
        ->and(User::query()->count())->toBe(0);
});

function userImportFile(array $rows, ?array $headers = null): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([($headers ?? [
        'Email',
        'Tipo di account',
        'Nome',
        'Cognome',
        'Prefisso nazionale',
        'Numero di telefono',
        'Codice fiscale',
        'Nazione di residenza/domicilio',
        'Regione di residenza/domicilio',
        'Provincia di residenza/domicilio',
        'Indirizzo di residenza/domicilio',
        'Codice postale di residenza/domicilio',
        'Data di nascita',
        'Luogo di nascita',
        'Genere',
        'Settore',
        'Categoria di lavoro',
        'Livello di inquadramento',
        'Ruolo',
        'Mansione (nome; separa con ;)',
        'Unità lavorativa (nome)',
        'Straniero',
        'Data di assunzione',
        'Data di cessazione',
        'Livello conoscenza lingua di lavoro',
    ])]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'user-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'utenti.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
