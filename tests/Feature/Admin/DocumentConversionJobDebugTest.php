<?php

use App\Enums\DocumentConversionJobStatus;
use App\Models\DocumentConversionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows superadmins to monitor document conversion jobs', function () {
    actingAsRole('superadmin');

    DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::PROCESSING,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/job-1.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/job-1.pdf',
        'attempts' => 1,
        'max_attempts' => 3,
        'started_at' => now(),
        'worker_id' => 'worker-01',
    ]);

    $this->get(route('admin.document-conversion-jobs.index'))
        ->assertOk()
        ->assertSeeText('Debug conversioni documenti')
        ->assertSeeText('In lavorazione')
        ->assertSeeText('worker-01')
        ->assertSeeText('certificates/word/job-1.docx')
        ->assertSee(route('admin.document-conversion-jobs.index'), escape: false);
});

it('forbids admins from accessing the document conversion debug page', function () {
    actingAsRole('admin');

    $this->get(route('admin.document-conversion-jobs.index'))
        ->assertRedirect(route('dashboard'));
});

it('allows superadmins to retry a retryable document conversion job', function () {
    Storage::fake('s3');
    actingAsRole('superadmin');

    Storage::disk('s3')->put('certificates/word/job-1.docx', 'source');

    $job = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::FAILED,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/job-1.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/job-1.pdf',
        'attempts' => 3,
        'max_attempts' => 3,
        'started_at' => now()->subMinute(),
        'failed_at' => now(),
        'error_message' => 'LibreOffice exited with code 1.',
        'worker_id' => 'worker-02',
    ]);

    $this->post(route('admin.document-conversion-jobs.retry', $job))
        ->assertRedirect(route('admin.document-conversion-jobs.index'))
        ->assertSessionHas('status', 'Job di conversione accodato nuovamente.');

    expect(DocumentConversionJob::query()->count())->toBe(2);

    $retriedJob = DocumentConversionJob::query()->latest('id')->firstOrFail();

    expect($retriedJob->status)->toBe(DocumentConversionJobStatus::PENDING)
        ->and($retriedJob->input_disk)->toBe('s3')
        ->and($retriedJob->input_path)->toBe('certificates/word/job-1.docx')
        ->and($retriedJob->output_path)->toBe('certificates/word/job-1.pdf')
        ->and($retriedJob->attempts)->toBe(0)
        ->and($retriedJob->worker_id)->toBeNull()
        ->and($retriedJob->started_at)->toBeNull()
        ->and($retriedJob->failed_at)->toBeNull()
        ->and($retriedJob->error_message)->toBeNull();
});

it('does not retry jobs that are still being processed', function () {
    Storage::fake('s3');
    actingAsRole('superadmin');

    Storage::disk('s3')->put('certificates/word/job-2.docx', 'source');

    $job = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::PROCESSING,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/job-2.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/job-2.pdf',
        'attempts' => 1,
        'max_attempts' => 3,
        'started_at' => now(),
    ]);

    $this->post(route('admin.document-conversion-jobs.retry', $job))
        ->assertRedirect(route('admin.document-conversion-jobs.index'))
        ->assertSessionHas('error', 'Questo job non può essere ripetuto.');

    expect(DocumentConversionJob::query()->count())->toBe(1);
});

it('allows superadmins to download the generated output file', function () {
    Storage::fake('s3');
    actingAsRole('superadmin');

    Storage::disk('s3')->put('certificates/word/job-3.pdf', 'pdf-content');

    $job = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::COMPLETED,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/job-3.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/job-3.pdf',
        'attempts' => 1,
        'max_attempts' => 3,
        'completed_at' => now(),
    ]);

    $this->get(route('admin.document-conversion-jobs.download', $job))
        ->assertDownload('job-3.pdf');
});

it('returns not found when the generated output file is missing', function () {
    Storage::fake('s3');
    actingAsRole('superadmin');

    $job = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::COMPLETED,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/job-4.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/job-4.pdf',
        'attempts' => 1,
        'max_attempts' => 3,
        'completed_at' => now(),
    ]);

    $this->get(route('admin.document-conversion-jobs.download', $job))
        ->assertNotFound();
});
