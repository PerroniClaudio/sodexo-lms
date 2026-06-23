<?php

use App\Jobs\ImportJobUnitsJob;
use App\Models\Importazione;
use App\Models\JobUnit;
use App\Services\JobUnitImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('queues a job unit import from excel upload', function () {
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.job-units.store'), [
        'file' => jobUnitImportFile([
            ['UNIT-001', 'Sede Roma Centro', 'IT', 'Lazio', 'Roma', 'Roma', 'Via Roma 1', '00100', 'Sede principale'],
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.job-units'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_JOB_UNITS)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('unita-lavorative.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportJobUnitsJob::class, fn (ImportJobUnitsJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads job unit import template', function () {
    actingAsRole('admin');

    ensureItalianGeography();

    $response = $this->get(route('admin.imports.job-units.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-unita-lavorative.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-job-unit-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Import unita lavorative');
    $lookupSheet = $spreadsheet->getSheetByName('Valori disponibili');

    expect($importSheet?->getCell('A2')->getValue())->toBe('UNIT-001')
        ->and($importSheet?->getCell('C2')->getValue())->toBe('IT')
        ->and($importSheet?->getCell('D2')->getValue())->toBe('Lazio')
        ->and($importSheet?->getCell('E2')->getValue())->toBe('Roma')
        ->and($importSheet?->getCell('F2')->getValue())->toBe('Roma')
        ->and($lookupSheet?->getCell('A2')->getValue())->toBe('IT');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('returns job unit import status card payload', function () {
    actingAsRole('admin');

    Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_UNITS,
        'created_by' => auth()->id(),
        'status' => Importazione::STATUS_PROGRESS,
        'file_path' => 'imports/job-units/progress.xlsx',
        'original_file_name' => 'progress-originale.xlsx',
    ]);

    $this->get(route('admin.imports.job-units.status-card'))
        ->assertOk()
        ->assertSeeText('Import unità lavorative recenti')
        ->assertSeeText('In lavorazione')
        ->assertSeeText('progress-originale.xlsx');
});

it('imports job units from excel', function () {
    Storage::fake('s3');

    [$countryId, $regionId, $provinceId, $cityId] = ensureItalianGeography();

    Storage::disk('s3')->put('imports/job-units/job-units.xlsx', file_get_contents(
        jobUnitImportFile([
            ['UNIT-001', 'Sede Roma Centro', 'IT', 'Lazio', 'Roma', 'Roma', 'Via Roma 1', '00100', 'Sede principale'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_UNITS,
        'file_path' => 'imports/job-units/job-units.xlsx',
        'original_file_name' => 'unita-lavorative-reali.xlsx',
    ]);

    app(ImportJobUnitsJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(JobUnitImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($importazione->fresh()->error_message)->toBeNull();

    $jobUnit = JobUnit::query()->where('unit_code', 'UNIT-001')->firstOrFail();

    expect($jobUnit->name)->toBe('Sede Roma Centro')
        ->and($jobUnit->country_id)->toBe($countryId)
        ->and($jobUnit->region_id)->toBe($regionId)
        ->and($jobUnit->province_id)->toBe($provinceId)
        ->and($jobUnit->city_id)->toBe($cityId)
        ->and($jobUnit->address)->toBe('Via Roma 1')
        ->and($jobUnit->postal_code)->toBe('00100')
        ->and($jobUnit->description)->toBe('Sede principale');
});

it('fails job unit import when required data is missing', function () {
    Storage::fake('s3');

    ensureItalianGeography();

    Storage::disk('s3')->put('imports/job-units/invalid-job-units.xlsx', file_get_contents(
        jobUnitImportFile([
            ['UNIT-001', 'Sede Roma Centro', 'IT', 'Lazio', 'Roma', 'Roma', 'Via Roma 1', null, 'Sede principale'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_UNITS,
        'file_path' => 'imports/job-units/invalid-job-units.xlsx',
        'original_file_name' => 'unita-lavorative-non-valide.xlsx',
    ]);

    app(ImportJobUnitsJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(JobUnitImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('Riga 2')
        ->and(JobUnit::query()->where('unit_code', 'UNIT-001')->exists())->toBeFalse();
});

function jobUnitImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([[
        'Codice unità lavorativa',
        'Nome',
        'Paese',
        'Regione',
        'Provincia',
        'Città',
        'Indirizzo',
        'Codice postale',
        'Breve descrizione',
    ]]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'job-unit-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'unita-lavorative.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}

/**
 * @return array{0: int, 1: int, 2: int, 3: int}
 */
function ensureItalianGeography(): array
{
    $continentId = DB::table('world_continents')->where('code', 'EU')->value('id')
        ?? DB::table('world_continents')->insertGetId([
            'name' => 'Europe',
            'code' => 'EU',
        ]);

    $countryId = DB::table('world_countries')->where('code', 'it')->value('id')
        ?? DB::table('world_countries')->insertGetId([
            'continent_id' => $continentId,
            'name' => 'Italy',
            'full_name' => 'Italian Republic',
            'capital' => 'Rome',
            'code' => 'it',
            'code_alpha3' => 'ITA',
            'emoji' => 'IT',
            'has_division' => true,
            'currency_code' => 'EUR',
            'currency_name' => 'Euro',
            'tld' => '.it',
            'callingcode' => '39',
        ]);

    $regionId = DB::table('world_divisions')->where('country_id', $countryId)->where('name', 'Lazio')->value('id')
        ?? DB::table('world_divisions')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Lazio',
            'full_name' => 'Lazio',
            'code' => '62',
            'has_city' => true,
        ]);

    $provinceId = DB::table('provinces')->where('country_id', $countryId)->where('region_id', $regionId)->where('name', 'Roma')->value('id')
        ?? DB::table('provinces')->insertGetId([
            'country_id' => $countryId,
            'region_id' => $regionId,
            'code' => 'RM',
            'name' => 'Roma',
        ]);

    $cityId = DB::table('world_cities')->where('country_id', $countryId)->where('division_id', $regionId)->where('province_id', $provinceId)->where('name', 'Roma')->value('id')
        ?? DB::table('world_cities')->insertGetId([
            'country_id' => $countryId,
            'division_id' => $regionId,
            'province_id' => $provinceId,
            'name' => 'Roma',
            'full_name' => 'Roma',
            'code' => 'H501',
        ]);

    return [$countryId, $regionId, $provinceId, $cityId];
}
