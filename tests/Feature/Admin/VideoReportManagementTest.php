<?php

use App\Jobs\GenerateVideoReport;
use App\Models\Course;
use App\Models\JobSector;
use App\Models\VideoReportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows admins to access video reports index', function () {
    actingAsRole('admin');

    $this->get(route('admin.video-reports.index'))
        ->assertOk()
        ->assertSeeText('Report video')
        ->assertSeeText('Richiedi report');
});

it('does not allow regular users to access video reports', function () {
    actingAsRole('user');

    $this->get(route('admin.video-reports.index'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('creates a queued video report request filtered by course', function () {
    Queue::fake();
    actingAsRole('admin');

    $course = Course::factory()->create(['title' => 'Corso video']);

    $this->post(route('admin.video-reports.store'), [
        'scope_type' => VideoReportRequest::SCOPE_COURSE,
        'course_id' => $course->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
    ])
        ->assertRedirect(route('admin.video-reports.index'))
        ->assertSessionHas('status', 'Richiesta report accodata con successo.');

    $videoReportRequest = VideoReportRequest::query()->sole();

    expect($videoReportRequest->scope_type)->toBe(VideoReportRequest::SCOPE_COURSE)
        ->and($videoReportRequest->course_id)->toBe($course->getKey())
        ->and($videoReportRequest->job_dimension)->toBeNull()
        ->and($videoReportRequest->status)->toBe(VideoReportRequest::STATUS_PENDING);

    Queue::assertPushed(GenerateVideoReport::class, function (GenerateVideoReport $job) use ($videoReportRequest): bool {
        return $job->videoReportRequest->is($videoReportRequest);
    });
});

it('creates a queued video report request filtered by job dimension', function () {
    Queue::fake();
    actingAsRole('admin');

    $jobSector = JobSector::factory()->create(['name' => 'Clinica']);

    $this->post(route('admin.video-reports.store'), [
        'scope_type' => VideoReportRequest::SCOPE_JOB_DIMENSION,
        'job_dimension' => VideoReportRequest::JOB_DIMENSION_SECTOR,
        'job_dimension_id' => $jobSector->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
    ])
        ->assertRedirect(route('admin.video-reports.index'));

    $videoReportRequest = VideoReportRequest::query()->sole();

    expect($videoReportRequest->scope_type)->toBe(VideoReportRequest::SCOPE_JOB_DIMENSION)
        ->and($videoReportRequest->course_id)->toBeNull()
        ->and($videoReportRequest->job_dimension)->toBe(VideoReportRequest::JOB_DIMENSION_SECTOR)
        ->and($videoReportRequest->job_dimension_id)->toBe($jobSector->getKey());
});

it('validates scope specific fields and date ordering', function () {
    actingAsRole('admin');

    $this->from(route('admin.video-reports.index'))
        ->post(route('admin.video-reports.store'), [
            'scope_type' => VideoReportRequest::SCOPE_JOB_DIMENSION,
            'date_from' => '2026-05-20',
            'date_to' => '2026-05-01',
        ])
        ->assertRedirect(route('admin.video-reports.index'))
        ->assertSessionHasErrors(['job_dimension', 'job_dimension_id', 'date_to']);
});

it('returns status payload for polling', function () {
    actingAsRole('admin');

    $videoReportRequest = VideoReportRequest::query()->create([
        'requested_by' => auth()->id(),
        'status' => VideoReportRequest::STATUS_PROCESSING,
        'scope_type' => VideoReportRequest::SCOPE_COURSE,
        'course_id' => Course::factory()->create()->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
        'output_disk' => 's3',
    ]);

    $this->get(route('admin.video-reports.show', $videoReportRequest))
        ->assertOk()
        ->assertJson([
            'id' => $videoReportRequest->getKey(),
            'status' => VideoReportRequest::STATUS_PROCESSING,
            'status_label' => 'In lavorazione',
            'is_terminal' => false,
            'download_url' => null,
        ]);
});

it('allows admins to download completed video report files', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $videoReportRequest = VideoReportRequest::query()->create([
        'requested_by' => auth()->id(),
        'status' => VideoReportRequest::STATUS_COMPLETED,
        'scope_type' => VideoReportRequest::SCOPE_COURSE,
        'course_id' => Course::factory()->create()->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
        'output_disk' => 's3',
        'output_path' => 'video-reports/1/report.xlsx',
        'completed_at' => now(),
    ]);

    Storage::disk('s3')->put($videoReportRequest->output_path, 'PK-xlsx-content');

    $response = $this->get(route('admin.video-reports.download', $videoReportRequest))
        ->assertDownload('report.xlsx');

    expect($response->streamedContent())->toBe('PK-xlsx-content');
});

it('returns not found when completed video report file is missing', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $videoReportRequest = VideoReportRequest::query()->create([
        'requested_by' => auth()->id(),
        'status' => VideoReportRequest::STATUS_COMPLETED,
        'scope_type' => VideoReportRequest::SCOPE_COURSE,
        'course_id' => Course::factory()->create()->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
        'output_disk' => 's3',
        'output_path' => 'video-reports/1/report.xlsx',
        'completed_at' => now(),
    ]);

    $this->get(route('admin.video-reports.download', $videoReportRequest))
        ->assertNotFound();
});
