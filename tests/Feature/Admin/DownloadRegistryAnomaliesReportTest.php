<?php

use App\Actions\BuildRegistryAnomaliesReport;
use App\Models\CompanyDivision;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

it('allows admins to download registry anomalies report', function () {
    actingAsRole('admin');

    Pdf::fake();

    $response = $this->get(route('admin.reports.registry-anomalies.download'));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        expect($pdf->viewName)->toBe('pdf.registry-anomalies-report')
            ->and($pdf->downloadName)->toBe('anomalie-anagrafica-'.now()->format('Ymd').'.pdf');

        return true;
    });
});

it('does not allow regular users to download registry anomalies report', function () {
    actingAsRole('user');

    $this->get(route('admin.reports.registry-anomalies.download'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('builds completeness, classification and operational anomaly details', function () {
    actingAsRole('admin');

    $task = JobTask::factory()->create([
        'name' => 'Mansione report '.Str::uuid(),
        'global_risk_level' => null,
    ]);
    $sector = JobSector::factory()->create(['name' => 'Settore report']);
    $worker = User::factory()->create([
        'name' => 'Luca',
        'surname' => 'Mancini',
        'job_task_id' => $task->getKey(),
        'job_sector_id' => $sector->getKey(),
        'job_role_id' => null,
    ]);

    $birthDate = now()->subYears(30)->startOfDay();
    User::factory()->asTeacher()->create([
        'name' => 'Paola',
        'surname' => 'Duplicata',
        'birth_date' => $birthDate,
    ]);
    User::factory()->asTutor()->create([
        'name' => 'Paola',
        'surname' => 'Duplicata',
        'birth_date' => $birthDate,
    ]);

    $report = app(BuildRegistryAnomaliesReport::class)(null);
    $taskRow = $report['job_tasks']->firstWhere('name', $task->name);

    expect($report['sections']['workers']['total'])->toBeGreaterThan(0)
        ->and($report['sections']['other_users']['total'])->toBeGreaterThan(1)
        ->and($report['anomalies']->pluck('category'))->toContain('Dati obbligatori mancanti')
        ->toContain('Mansione non classificata')
        ->toContain('Possibile duplicato anagrafico')
        ->and($taskRow['users_count'])->toBe(1)
        ->and($taskRow['classified'])->toBeFalse()
        ->and($report['anomalies']->pluck('user_name'))->toContain($worker->surname.' '.$worker->name);
});

it('limits report data to selected company division', function () {
    actingAsRole('admin');

    $includedDivision = CompanyDivision::factory()->create();
    $excludedDivision = CompanyDivision::factory()->create();
    $includedUser = User::factory()->create([
        'name' => 'Inclusa',
        'surname' => 'Divisione',
        'company_division_id' => $includedDivision->getKey(),
        'job_role_id' => null,
    ]);
    User::factory()->create([
        'name' => 'Esclusa',
        'surname' => 'Divisione',
        'company_division_id' => $excludedDivision->getKey(),
    ]);

    $report = app(BuildRegistryAnomaliesReport::class)($includedDivision->getKey());

    expect($report['scope_label'])->toBe('divisione attiva')
        ->and($report['anomalies']->pluck('user_name')->join(' '))->toContain($includedUser->surname.' '.$includedUser->name)
        ->not->toContain('Divisione Esclusa');
});
