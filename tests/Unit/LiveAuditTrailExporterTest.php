<?php

use App\Jobs\GenerateVideoReport;
use App\Models\Course;
use App\Models\JobSector;
use App\Models\LiveStreamAuditEvent;
use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\User;
use App\Models\VideoReportRequest;
use App\Services\LiveAuditTrailExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('builds a live audit workbook filtered by date range', function () {
    Storage::fake('s3');
    actingAsRole('admin');
    app()->setLocale('it');

    [$videoReportRequest] = seedLiveAuditFixture();

    $exporter = app(LiveAuditTrailExporter::class);
    $bytes = $exporter->buildWorkbookContents($videoReportRequest);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'live-audit-');
    file_put_contents($temporaryFile, $bytes);

    expect(zipCanOpen($temporaryFile))->toBeTrue();

    $spreadsheet = IOFactory::load($temporaryFile);

    expect($spreadsheet->getSheetCount())->toBe(1)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('Audit Trail')
        ->and($spreadsheet->getSheet(0)->getCell('A1')->getValue())->toBe('ID corso')
        ->and($spreadsheet->getSheet(0)->getCell('P1')->getValue())->toBe('Contesto')
        ->and($spreadsheet->getSheet(0)->getCell('B2')->getValue())->toBe('Corso audit live')
        ->and($spreadsheet->getSheet(0)->getCell('N2')->getValue())->toBe(LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED)
        ->and($spreadsheet->getSheet(0)->getHighestDataRow())->toBe(3);

    @unlink($temporaryFile);
});

it('stores a live audit trail file when queued job succeeds', function () {
    Storage::fake('s3');
    actingAsRole('admin');

    [$videoReportRequest] = seedLiveAuditFixture();
    $videoReportRequest->forceFill(['report_type' => VideoReportRequest::REPORT_TYPE_LIVE])->save();

    GenerateVideoReport::dispatchSync($videoReportRequest);

    $videoReportRequest->refresh();

    expect($videoReportRequest->status)->toBe(VideoReportRequest::STATUS_COMPLETED)
        ->and($videoReportRequest->output_path)->toContain('live-audit-trails/')
        ->and($videoReportRequest->completed_at)->not->toBeNull();

    Storage::disk('s3')->assertExists($videoReportRequest->output_path);
});

function seedLiveAuditFixture(): array
{
    $course = Course::factory()->create([
        'title' => 'Corso audit live',
        'type' => 'async',
        'status' => 'draft',
    ]);

    $module = Module::factory()->create([
        'title' => 'Modulo live',
        'type' => Module::TYPE_LIVE,
        'belongsTo' => (string) $course->getKey(),
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

    $teacher = User::query()->create([
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
    $teacher->assignRole(Role::findByName('teacher'));

    $jobSector = JobSector::factory()->create(['name' => 'Settore live']);
    $user->forceFill(['job_sector_id' => $jobSector->getKey()])->saveQuietly();

    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'teacher_user_id' => $teacher->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
        'started_at' => Carbon::parse('2026-05-10 10:00:00'),
        'ended_at' => null,
    ]);

    $participant = LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $user->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'joined_at' => Carbon::parse('2026-05-10 10:01:00'),
        'last_seen_at' => Carbon::parse('2026-05-10 10:01:45'),
    ]);

    $handRaise = LiveStreamHandRaise::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $user->getKey(),
        'status' => LiveStreamHandRaise::STATUS_PENDING,
        'requested_at' => Carbon::parse('2026-05-10 10:05:00'),
    ]);

    LiveStreamAuditEvent::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'live_stream_participant_id' => $participant->getKey(),
        'event_type' => LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED,
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'occurred_at' => Carbon::parse('2026-05-10 10:01:00'),
        'context' => ['source' => 'join'],
    ]);

    LiveStreamAuditEvent::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'live_stream_participant_id' => $participant->getKey(),
        'live_stream_hand_raise_id' => $handRaise->getKey(),
        'event_type' => LiveStreamAuditEvent::TYPE_HAND_RAISE_REQUESTED,
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'occurred_at' => Carbon::parse('2026-05-10 10:05:00'),
        'context' => ['status' => LiveStreamHandRaise::STATUS_PENDING],
    ]);

    LiveStreamAuditEvent::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'live_stream_participant_id' => $participant->getKey(),
        'event_type' => LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED,
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'occurred_at' => Carbon::parse('2026-06-10 10:10:00'),
        'context' => ['reason' => 'out_of_range'],
    ]);

    $videoReportRequest = VideoReportRequest::query()->create([
        'requested_by' => auth()->id(),
        'status' => VideoReportRequest::STATUS_PENDING,
        'report_type' => VideoReportRequest::REPORT_TYPE_LIVE,
        'scope_type' => VideoReportRequest::SCOPE_COURSE,
        'course_id' => $course->getKey(),
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-20',
        'output_disk' => 's3',
    ]);

    return [$videoReportRequest, $session, $jobSector];
}
