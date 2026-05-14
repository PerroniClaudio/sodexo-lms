<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AsyncLiveCourseDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COURSE_TITLE = 'Corso demo FAD asincrono con live';

    private const TEACHER_EMAIL = 'docente-live-async-demo@test.com';

    private const USER_EMAIL = 'utente-live-async-demo@test.com';

    private const DEFAULT_PASSWORD = 'Sodexo@Test.26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $course = $this->createCourse();
        $this->createLiveModule($course);

        $teacher = $this->upsertUser(
            role: 'teacher',
            email: self::TEACHER_EMAIL,
            name: 'Docente',
            surname: 'Live Async Demo',
            fiscalCode: 'DCLASY80A01H501Q',
        );

        $user = $this->upsertUser(
            role: 'user',
            email: self::USER_EMAIL,
            name: 'Utente',
            surname: 'Live Async Demo',
            fiscalCode: 'UTLASY80A01H501R',
        );

        $this->enrollTeacher($teacher, $course);
        $this->enrollUser($user, $course);
    }

    private function createCourse(): Course
    {
        $course = Course::query()->firstOrNew([
            'title' => self::COURSE_TITLE,
        ]);

        $course->fill([
            'description' => 'Corso FAD asincrono di esempio con un modulo live pubblicato.',
            'type' => 'async',
            'year' => (int) now()->year,
            'expiry_date' => now()->addYear(),
            'status' => 'published',
            'hasMany' => '1',
        ]);

        $course->save();

        return $course;
    }

    private function createLiveModule(Course $course): Module
    {
        $appointmentStart = Carbon::today()->setHour(15)->setMinute(0)->setSecond(0);

        return Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 1,
            ],
            [
                'title' => 'Modulo live demo',
                'description' => 'Sessione live pubblicata all\'interno di un corso FAD asincrono.',
                'type' => 'live',
                'is_live_teacher' => true,
                'appointment_date' => $appointmentStart->copy(),
                'appointment_start_time' => $appointmentStart->copy(),
                'appointment_end_time' => $appointmentStart->copy()->addHour(),
                'status' => 'published',
                'passing_score' => null,
                'max_score' => null,
            ]
        );
    }

    private function upsertUser(
        string $role,
        string $email,
        string $name,
        string $surname,
        string $fiscalCode,
    ): User {
        $verifiedAt = now();

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'email_verified_at' => $verifiedAt,
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => $verifiedAt,
            'fiscal_code' => $fiscalCode,
        ]);

        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }

    private function enrollTeacher(User $teacher, Course $course): void
    {
        $alreadyAssigned = CourseTeacherEnrollment::query()
            ->where('user_id', $teacher->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyAssigned) {
            return;
        }

        CourseTeacherEnrollment::enroll($teacher, $course);
    }

    private function enrollUser(User $user, Course $course): void
    {
        $alreadyEnrolled = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyEnrolled) {
            return;
        }

        CourseEnrollment::enroll($user, $course);
    }
}
