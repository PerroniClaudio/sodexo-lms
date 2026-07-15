<?php

use App\Enums\DocumentConversionJobStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DocumentConversionJob;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake();
});

it('shows completed courses in user sidebar page with certificate download button', function () {
    $user = completedCoursesTestUser();
    $user->assignRole('user');

    $completedCourse = Course::factory()->create([
        'title' => 'Corso Sicurezza',
    ]);

    $completedEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $completedCourse->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    Storage::put(
        completedCertificatePath($completedEnrollment, $user->fiscal_code, 'participation'),
        'pdf-content'
    );

    Storage::put(
        completedCertificatePath($completedEnrollment, $user->fiscal_code, 'completion'),
        'pdf-content'
    );

    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => Course::factory()->create([
            'title' => 'Corso In Corso',
        ])->getKey(),
        'status' => CourseEnrollment::STATUS_IN_PROGRESS,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.completed-courses.index'));

    $response->assertOk();
    $response->assertSeeText('Corsi completati');
    $response->assertSeeText('Corso Sicurezza');
    $response->assertSeeText('Scarica attestato partecipazione');
    $response->assertSeeText('Scarica attestato superamento');
    $response->assertDontSeeText('Corso In Corso');
    $response->assertSee(route('user.completed-courses.certificate.download', ['courseEnrollment' => $completedEnrollment, 'type' => 'participation']), false);
    $response->assertSee(route('user.completed-courses.certificate.download', ['courseEnrollment' => $completedEnrollment, 'type' => 'completion']), false);
});

it('shows pending certificate generation message when conversion job is still running', function () {
    $user = completedCoursesTestUser();
    $user->assignRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso Sicurezza',
    ]);

    $enrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::PROCESSING,
        'input_disk' => 'local',
        'input_path' => 'certificates/word/source.docx',
        'output_disk' => 'local',
        'output_path' => completedCertificatePath($enrollment, $user->fiscal_code, 'participation'),
    ]);

    $this->actingAs($user)
        ->get(route('user.completed-courses.index'))
        ->assertOk()
        ->assertSeeText('Attestato in fase di generazione')
        ->assertDontSeeText('Attestato non disponibile');
});

it('downloads participation certificate for completed course owned by authenticated user', function () {
    $user = completedCoursesTestUser();
    $user->assignRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso Privacy',
    ]);

    $enrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    Storage::put(
        completedCertificatePath($enrollment, $user->fiscal_code, 'participation'),
        'pdf-content'
    );

    $this->actingAs($user)
        ->get(route('user.completed-courses.certificate.download', ['courseEnrollment' => $enrollment, 'type' => 'participation']))
        ->assertOk()
        ->assertDownload('attestato-partecipazione-corso-'.$course->getKey().'-'.now()->format('Ymd').'.pdf');
});

it('downloads completion certificate for completed course owned by authenticated user', function () {
    $user = completedCoursesTestUser();
    $user->assignRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso Privacy',
    ]);

    $enrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    Storage::put(
        completedCertificatePath($enrollment, $user->fiscal_code, 'completion'),
        'pdf-content'
    );

    $this->actingAs($user)
        ->get(route('user.completed-courses.certificate.download', ['courseEnrollment' => $enrollment, 'type' => 'completion']))
        ->assertOk()
        ->assertDownload('attestato-superamento-corso-'.$course->getKey().'-'.now()->format('Ymd').'.pdf');
});

it('does not allow downloading another users certificate', function () {
    $user = completedCoursesTestUser('mario@example.com', 'RSSMRA80A01H501Z');
    $user->assignRole('user');

    $otherUser = completedCoursesTestUser('laura@example.com', 'BNCLRA80A01H501Z');
    $otherUser->assignRole('user');

    $course = Course::factory()->create();

    $otherEnrollment = CourseEnrollment::factory()->create([
        'user_id' => $otherUser->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    Storage::put(
        completedCertificatePath($otherEnrollment, $otherUser->fiscal_code, 'participation'),
        'pdf-content'
    );

    $this->actingAs($user)
        ->get(route('user.completed-courses.certificate.download', ['courseEnrollment' => $otherEnrollment, 'type' => 'participation']))
        ->assertNotFound();
});

it('switches from teacher active role to user completed courses without showing role selection', function () {
    $user = completedCoursesTestUser('multiuser@example.com', 'MLTUSR80A01H501F');
    $user->syncRoles(['user', 'teacher']);

    $this->withSession(['active_role' => 'teacher'])
        ->actingAs($user)
        ->get(route('teacher.dashboard'))
        ->assertSee(route('role.switch', ['role' => 'user', 'redirect_route' => 'user.completed-courses.index']), false);

    $this->withSession(['active_role' => 'teacher'])
        ->actingAs($user)
        ->get(route('role.switch', ['role' => 'user', 'redirect_route' => 'user.completed-courses.index']))
        ->assertRedirect(route('user.completed-courses.index'))
        ->assertSessionHas('active_role', 'user');
});

function completedCoursesTestUser(string $email = 'user@example.com', string $fiscalCode = 'RSSMRA80A01H501Z'): User
{
    return User::forceCreate([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => $email,
        'password' => Hash::make('password'),
        'fiscal_code' => $fiscalCode,
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
}

function completedCertificatePath(CourseEnrollment $enrollment, string $fiscalCode, string $type): string
{
    return sprintf(
        'certificates/word/%s_%s_%s_%s.pdf',
        $enrollment->course_id,
        Str::upper(Str::of($fiscalCode)->replaceMatches('/[^A-Za-z0-9]/', '')->value()),
        $enrollment->completed_at?->format('Ymd'),
        $type
    );
}
