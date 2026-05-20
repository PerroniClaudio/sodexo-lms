<?php

use App\Jobs\GenerateVideoReport;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\JobSector;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoReportRequest;
use App\Models\VideoTrackingEvent;
use App\Services\VideoReportExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('builds a workbook with progress and audit sheets filtered by date range', function () {
    Storage::fake('s3');
    actingAsRole('admin');
    app()->setLocale('it');

    [$videoReportRequest, $moduleProgress] = seedVideoReportFixture();

    $exporter = app(VideoReportExporter::class);
    $bytes = $exporter->buildWorkbookContents($videoReportRequest);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'video-report-');
    file_put_contents($temporaryFile, $bytes);

    expect(zipCanOpen($temporaryFile))->toBeTrue();

    $spreadsheet = IOFactory::load($temporaryFile);

    expect($spreadsheet->getSheetCount())->toBe(2)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('Avanzamenti')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('Audit Trail')
        ->and($spreadsheet->getSheet(0)->getCell('A1')->getValue())->toBe('ID corso')
        ->and($spreadsheet->getSheet(0)->getCell('R1')->getValue())->toBe('Stato modulo')
        ->and($spreadsheet->getSheet(1)->getCell('N1')->getValue())->toBe('Tipo evento')
        ->and($spreadsheet->getSheet(0)->getCell('B2')->getValue())->toBe('Corso report video')
        ->and($spreadsheet->getSheet(0)->getCell('R2')->getValue())->toBe(ModuleProgress::STATUS_COMPLETED)
        ->and($spreadsheet->getSheet(1)->getCell('N2')->getValue())->toBe(VideoTrackingEvent::TYPE_PLAY)
        ->and($spreadsheet->getSheet(1)->getHighestDataRow())->toBe(2);

    @unlink($temporaryFile);
});

it('filters workbook rows by selected job dimension', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    $jobSector = JobSector::factory()->create(['name' => 'Settore incluso']);
    $otherSector = JobSector::factory()->create(['name' => 'Settore escluso']);

    [$videoReportRequest] = seedVideoReportFixture($jobSector);
    seedVideoReportFixture($otherSector, 'Corso escluso');

    $videoReportRequest->forceFill([
        'scope_type' => VideoReportRequest::SCOPE_JOB_DIMENSION,
        'course_id' => null,
        'job_dimension' => VideoReportRequest::JOB_DIMENSION_SECTOR,
        'job_dimension_id' => $jobSector->getKey(),
    ])->save();

    $exporter = app(VideoReportExporter::class);
    $bytes = $exporter->buildWorkbookContents($videoReportRequest);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'video-report-');
    file_put_contents($temporaryFile, $bytes);

    expect(zipCanOpen($temporaryFile))->toBeTrue();

    $spreadsheet = IOFactory::load($temporaryFile);

    expect($spreadsheet->getSheet(0)->getHighestDataRow())->toBe(2)
        ->and($spreadsheet->getSheet(0)->getCell('L2')->getValue())->toBe('Settore incluso')
        ->and($spreadsheet->getSheet(1)->getHighestDataRow())->toBe(2);

    @unlink($temporaryFile);
});

it('marks report completed and stores file on s3 when queued job succeeds', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    [$videoReportRequest] = seedVideoReportFixture();

    GenerateVideoReport::dispatchSync($videoReportRequest);

    $videoReportRequest->refresh();

    expect($videoReportRequest->status)->toBe(VideoReportRequest::STATUS_COMPLETED)
        ->and($videoReportRequest->output_path)->not->toBeNull()
        ->and($videoReportRequest->output_path)->toEndWith('.xlsx')
        ->and($videoReportRequest->completed_at)->not->toBeNull();

    Storage::disk('s3')->assertExists($videoReportRequest->output_path);

    $temporaryFile = tempnam(sys_get_temp_dir(), 'stored-video-report-');
    $contents = Storage::disk('s3')->get($videoReportRequest->output_path);

    expect($contents)->toStartWith('PK');

    file_put_contents($temporaryFile, $contents);

    expect(zipCanOpen($temporaryFile))->toBeTrue();

    $spreadsheet = IOFactory::load($temporaryFile);

    expect($spreadsheet->getSheet(0)->getTitle())->toBe('Avanzamenti')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('Audit Trail');

    @unlink($temporaryFile);
});

it('marks report failed when exporter throws an exception', function () {
    actingAsRole('admin');

    [$videoReportRequest] = seedVideoReportFixture();

    $this->mock(VideoReportExporter::class, function ($mock): void {
        $mock->shouldReceive('store')
            ->once()
            ->andThrow(new RuntimeException('Export failed.'));
    });

    expect(fn () => GenerateVideoReport::dispatchSync($videoReportRequest))
        ->toThrow(RuntimeException::class, 'Export failed.');

    $videoReportRequest->refresh();

    expect($videoReportRequest->status)->toBe(VideoReportRequest::STATUS_FAILED)
        ->and($videoReportRequest->error_message)->toBe('Export failed.')
        ->and($videoReportRequest->completed_at)->not->toBeNull();
});

function seedVideoReportFixture(?JobSector $jobSector = null, string $courseTitle = 'Corso report video'): array
{
    $video = Video::factory()->create([
        'title' => 'Video principale',
        'duration_seconds' => 180,
    ]);

    $course = Course::factory()->create([
        'title' => $courseTitle,
        'type' => 'fad',
        'status' => 'draft',
    ]);

    $module = Module::factory()->create([
        'title' => 'Modulo video',
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
        'order' => 1,
    ]);

    $user = User::query()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole(Role::findByName('user'));

    $jobSector ??= JobSector::factory()->create(['name' => 'Settore report']);
    $user->forceFill(['job_sector_id' => $jobSector->getKey()])->saveQuietly();

    $enrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'current_module_id' => $module->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ]);

    $moduleProgress = ModuleProgress::factory()->create([
        'course_user_id' => $enrollment->getKey(),
        'module_id' => $module->getKey(),
        'status' => ModuleProgress::STATUS_COMPLETED,
        'time_spent_seconds' => 120,
        'video_current_second' => 100,
        'video_max_second' => 170,
        'started_at' => now()->subDay(),
        'last_accessed_at' => now()->subHour(),
        'completed_at' => now()->subMinutes(30),
    ]);

    VideoTrackingEvent::query()->create([
        'module_progress_id' => $moduleProgress->getKey(),
        'course_user_id' => $enrollment->getKey(),
        'module_id' => $module->getKey(),
        'video_id' => $video->getKey(),
        'user_id' => $user->getKey(),
        'session_uuid' => (string) Str::uuid(),
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_PLAY,
        'position_second' => 25,
        'max_second_client' => 25,
        'delta_watched_seconds' => 25,
        'from_second' => 0,
        'to_second' => 25,
        'player_ended' => false,
        'was_blocked' => false,
        'occurred_at' => '2026-05-10 10:00:00',
    ]);

    VideoTrackingEvent::query()->create([
        'module_progress_id' => $moduleProgress->getKey(),
        'course_user_id' => $enrollment->getKey(),
        'module_id' => $module->getKey(),
        'video_id' => $video->getKey(),
        'user_id' => $user->getKey(),
        'session_uuid' => (string) Str::uuid(),
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_PAUSE,
        'position_second' => 40,
        'max_second_client' => 40,
        'delta_watched_seconds' => 15,
        'from_second' => 25,
        'to_second' => 40,
        'player_ended' => false,
        'was_blocked' => false,
        'occurred_at' => '2026-06-10 10:00:00',
    ]);

    $videoReportRequest = VideoReportRequest::query()->create([
        'requested_by' => auth()->id(),
        'status' => VideoReportRequest::STATUS_PENDING,
        'report_type' => VideoReportRequest::REPORT_TYPE_VIDEO,
        'scope_type' => VideoReportRequest::SCOPE_COURSE,
        'course_id' => $course->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
        'output_disk' => 's3',
    ]);

    return [$videoReportRequest, $moduleProgress, $jobSector];
}

function zipCanOpen(string $path): bool
{
    $zip = new ZipArchive;
    $opened = $zip->open($path) === true;

    if ($opened) {
        $zip->close();
    }

    return $opened;
}
