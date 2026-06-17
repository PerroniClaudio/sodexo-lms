<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResCourseParticipantsDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COURSE_TITLE = 'Corso demo RES con docente, tutor e tre utenti';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $course = $this->createCourse();
            $this->createModule($course);

            $teacher = $this->upsertUser(
                role: 'teacher',
                email: 'docente.res@test.com',
                password: 'docente1',
                name: 'Docente',
                surname: 'RES',
                fiscalCode: 'DCTRES80A01H501A',
            );

            $tutor = $this->upsertUser(
                role: 'tutor',
                email: 'tutor.res@test.com',
                password: 'tutor1',
                name: 'Tutor',
                surname: 'RES',
                fiscalCode: 'TTRRES80A01H501B',
            );

            $users = [
                $this->upsertUser(
                    role: 'user',
                    email: 'utente1.res@test.com',
                    password: 'utente1',
                    name: 'Utente1',
                    surname: 'RES',
                    fiscalCode: 'UTERES80A01H501C',
                ),
                $this->upsertUser(
                    role: 'user',
                    email: 'utente2.res@test.com',
                    password: 'utente2',
                    name: 'Utente2',
                    surname: 'RES',
                    fiscalCode: 'UTERES80A01H501D',
                ),
                $this->upsertUser(
                    role: 'user',
                    email: 'utente3.res@test.com',
                    password: 'utente3',
                    name: 'Utente3',
                    surname: 'RES',
                    fiscalCode: 'UTERES80A01H501E',
                ),
            ];

            $this->assignTeacher($teacher, $course);
            $this->assignTutor($tutor, $course);

            foreach ($users as $user) {
                $this->enrollUser($user, $course);
            }
        });
    }

    private function createCourse(): Course
    {
        $course = Course::query()->firstOrNew([
            'title' => self::COURSE_TITLE,
        ]);

        $course->fill([
            'description' => 'Corso demo RES con un docente, un tutor e tre partecipanti di test.',
            'type' => 'res',
            'year' => (int) now()->year,
            'expiry_date' => now()->addYear(),
            'status' => 'published',
            'hasMany' => '1',
        ]);

        $course->save();

        return $course;
    }

    private function createModule(Course $course): Module
    {
        $appointmentStart = Carbon::today()->addWeek()->setHour(9)->setMinute(0)->setSecond(0);

        return Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 1,
            ],
            [
                'title' => 'Modulo RES demo',
                'description' => 'Sessione residenziale di esempio per docente, tutor e partecipanti.',
                'type' => 'res',
                'is_live_teacher' => false,
                'appointment_date' => $appointmentStart->copy(),
                'appointment_start_time' => $appointmentStart->copy(),
                'appointment_end_time' => $appointmentStart->copy()->addHours(8),
                'status' => 'published',
                'passing_score' => null,
                'max_score' => null,
            ]
        );
    }

    private function upsertUser(
        string $role,
        string $email,
        string $password,
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
            'password' => $password,
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => $verifiedAt,
            'fiscal_code' => $fiscalCode,
        ]);

        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }

    private function assignTeacher(User $teacher, Course $course): void
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

    private function assignTutor(User $tutor, Course $course): void
    {
        $alreadyAssigned = CourseTutorEnrollment::query()
            ->where('user_id', $tutor->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyAssigned) {
            return;
        }

        CourseTutorEnrollment::enroll($tutor, $course);
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
