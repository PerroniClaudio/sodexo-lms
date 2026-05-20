<?php

use App\Enums\DocumentConversionJobStatus;
use App\Jobs\GenerateCourseCertificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\DocumentConversionJob;
use App\Models\Module;
use App\Models\User;
use App\Services\Certificates\CourseCertificateGenerator;
use App\Services\CloudRunJobClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('queues certificate generation when enrollment completes on final module without required satisfaction survey', function () {
    Queue::fake();

    [$enrollment, $videoProgress] = createCertificateEnrollment(false);

    $videoProgress->markCompleted();

    $enrollment->refresh();

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);

    Queue::assertPushed(GenerateCourseCertificate::class, function (GenerateCourseCertificate $job) use ($enrollment): bool {
        return $job->courseEnrollment->is($enrollment);
    });
});

it('queues certificate generation when required satisfaction survey completes enrollment', function () {
    Queue::fake();

    [$enrollment, $videoProgress, $surveyProgress] = createCertificateEnrollment(true);

    $videoProgress->markCompleted();
    $surveyProgress->refresh();
    $surveyProgress->completeSatisfactionSurvey();

    $enrollment->refresh();

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);

    Queue::assertPushed(GenerateCourseCertificate::class, function (GenerateCourseCertificate $job) use ($enrollment): bool {
        return $job->courseEnrollment->is($enrollment);
    });
});

it('creates pending document conversion job for completed enrollment using active matching template', function () {
    Storage::fake('s3');

    [$enrollment, $videoProgress] = createCertificateEnrollment(false);
    $videoProgress->markCompleted();
    $enrollment->refresh();

    $templateUpload = docxUpload([
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r><w:t>${TITOLO}</w:t></w:r>
        </w:p>
    </w:body>
</w:document>
XML,
    ]);

    $storedPath = $templateUpload->store('custom-certificates/participation', 's3');

    CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'storage_disk' => 's3',
        'template_path' => $storedPath,
        'original_filename' => 'template.docx',
        'course_ids' => [$enrollment->course_id],
    ]);

    $job = app(CourseCertificateGenerator::class)->generateForEnrollment($enrollment);

    expect($job)->not->toBeNull()
        ->and($job?->status)->toBe(DocumentConversionJobStatus::PENDING)
        ->and($job?->input_disk)->toBe('s3')
        ->and($job?->output_disk)->toBe('s3')
        ->and($job?->input_path)->toBe('certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'.docx')
        ->and($job?->output_path)->toBe('certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'.pdf');

    Storage::disk('s3')->assertExists($job->input_path);
});

it('starts pending document conversion jobs from scheduler command', function () {
    $pendingJob = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::PENDING,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/pending.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/pending.pdf',
    ]);

    $failedJob = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::FAILED,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/failed.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/failed.pdf',
    ]);

    $this->mock(CloudRunJobClient::class, function ($mock) use ($pendingJob): void {
        $mock->shouldReceive('runDocumentConversionJob')
            ->once()
            ->withArgs(fn (DocumentConversionJob $job): bool => $job->is($pendingJob)
                && $job->status === DocumentConversionJobStatus::PENDING
                && $job->attempts === 0
                && $job->started_at === null)
            ->andReturn([
                'operation_name' => 'operations/course-certificate',
                'payload' => ['name' => 'operations/course-certificate'],
            ]);
    });

    Artisan::call('app:start-pending-document-conversion-jobs');

    expect($pendingJob->fresh())
        ->status->toBe(DocumentConversionJobStatus::PENDING)
        ->and($pendingJob->fresh()->attempts)->toBe(0)
        ->and($failedJob->fresh()->status)->toBe(DocumentConversionJobStatus::FAILED);
});

function createCertificateEnrollment(bool $requiresSatisfactionSurvey): array
{
    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Certificate',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);

    $course = Course::factory()->create([
        'has_satisfaction_survey' => true,
        'satisfaction_survey_required_for_certificate' => $requiresSatisfactionSurvey,
    ]);

    $videoModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $surveyModule = Module::factory()->create([
        'type' => Module::TYPE_SATISFACTION_QUIZ,
        'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    $videoProgress = $enrollment->moduleProgresses()->where('module_id', $videoModule->getKey())->firstOrFail();
    $surveyProgress = $enrollment->moduleProgresses()->where('module_id', $surveyModule->getKey())->firstOrFail();

    return [$enrollment, $videoProgress, $surveyProgress];
}
