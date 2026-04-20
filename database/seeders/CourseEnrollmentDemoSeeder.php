<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CourseEnrollmentDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COURSE_TITLE = 'Corso demo iscrizioni';

    private const DOCENTE_EMAIL = 'docente-corso-demo@test.com';

    private const USER_EMAIL = 'utente-corso-demo@test.com';

    private const DEFAULT_PASSWORD = 'Sodexo@Test.26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $course = $this->createCourse();

        $this->createModules($course);

        $docente = $this->upsertUser(
            role: 'docente',
            email: self::DOCENTE_EMAIL,
            name: 'Docente',
            surname: 'Demo',
            fiscalCode: 'DOCDEM80A01H501Z',
        );

        $user = $this->upsertUser(
            role: 'user',
            email: self::USER_EMAIL,
            name: 'Utente',
            surname: 'Demo',
            fiscalCode: 'UTEDEM80A01H501Y',
        );

        $this->enrollUser($docente, $course);
        $this->enrollUser($user, $course);
    }

    private function createCourse(): Course
    {
        $course = Course::query()->firstOrNew([
            'title' => self::COURSE_TITLE,
        ]);

        $course->fill([
            'description' => 'Corso demo con modulo live, quiz di apprendimento e quiz di gradimento.',
            'type' => 'blended',
            'year' => (int) now()->year,
            'expiry_date' => now()->addYear(),
            'status' => 'published',
            'hasMany' => '3',
        ]);

        $course->save();

        return $course;
    }

    private function createModules(Course $course): void
    {
        $liveAppointment = Carbon::tomorrow()->setHour(9)->setMinute(0)->setSecond(0);
        $quizAppointment = $liveAppointment->copy()->addDay()->setHour(14);
        $satisfactionAppointment = $quizAppointment->copy()->addHour();

        Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 1,
            ],
            [
                'title' => 'Modulo live con docente',
                'description' => 'Sessione live erogata da un docente.',
                'type' => 'live',
                'is_live_teacher' => true,
                'appointment_date' => $liveAppointment,
                'appointment_start_time' => $liveAppointment->copy(),
                'appointment_end_time' => $liveAppointment->copy()->addHour(),
                'status' => 'published',
                'passing_score' => null,
                'max_score' => null,
            ]
        );

        Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 2,
            ],
            [
                'title' => 'Quiz finale di apprendimento',
                'description' => 'Quiz per verificare l’apprendimento del corso.',
                'type' => 'learning_quiz',
                'is_live_teacher' => false,
                'appointment_date' => $quizAppointment,
                'appointment_start_time' => $quizAppointment->copy(),
                'appointment_end_time' => $quizAppointment->copy()->addMinutes(30),
                'status' => 'published',
                'passing_score' => 7,
                'max_score' => 10,
            ]
        );

        Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 3,
            ],
            [
                'title' => 'Questionario di gradimento',
                'description' => 'Quiz di gradimento conclusivo.',
                'type' => 'satisfaction_quiz',
                'is_live_teacher' => false,
                'appointment_date' => $satisfactionAppointment,
                'appointment_start_time' => $satisfactionAppointment->copy(),
                'appointment_end_time' => $satisfactionAppointment->copy()->addMinutes(15),
                'status' => 'published',
                'passing_score' => 1,
                'max_score' => 1,
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
        $user = User::query()->firstOrNew(['email' => $email]);

        $verifiedAt = now();

        $user->forceFill([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'email_verified_at' => $verifiedAt,
            'password' => self::DEFAULT_PASSWORD,
            'account_type' => $role,
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => $verifiedAt,
            'fiscal_code' => $fiscalCode,
        ]);

        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }

    private function enrollUser(User $user, Course $course): void
    {
        $existingEnrollment = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->exists();

        if ($existingEnrollment) {
            return;
        }

        CourseEnrollment::enroll($user, $course);
    }
}
