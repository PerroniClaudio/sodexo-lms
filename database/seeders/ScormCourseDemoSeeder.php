<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScormCourseDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COURSE_TITLE = 'Corso demo SCORM';

    private const USER_EMAIL = 'utente-scorm-demo@test.com';

    private const DEFAULT_PASSWORD = 'Sodexo@Test.26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $course = $this->createCourse();
        $module = $this->createScormModule($course);
        $user = $this->upsertUser();

        $this->enrollUser($user, $course, $module);
    }

    private function createCourse(): Course
    {
        $course = Course::query()->firstOrNew([
            'title' => self::COURSE_TITLE,
        ]);

        $course->fill([
            'description' => 'Corso demo con un modulo SCORM pubblicato.',
            'type' => 'async',
            'year' => (int) now()->year,
            'expiry_date' => now()->addYear(),
            'status' => 'published',
            'hasMany' => '1',
        ]);

        $course->save();

        return $course;
    }

    private function createScormModule(Course $course): Module
    {
        return Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 1,
            ],
            [
                'title' => 'Modulo SCORM demo',
                'description' => 'Modulo predisposto per il caricamento di un pacchetto SCORM.',
                'type' => 'scorm',
                'is_live_teacher' => false,
                'appointment_date' => now(),
                'appointment_start_time' => now(),
                'appointment_end_time' => now()->addHour(),
                'status' => 'published',
                'passing_score' => null,
                'max_score' => null,
            ]
        );
    }

    private function upsertUser(): User
    {
        $verifiedAt = now();

        $user = User::query()->firstOrNew([
            'email' => self::USER_EMAIL,
        ]);

        $user->forceFill([
            'name' => 'Utente',
            'surname' => 'Scorm Demo',
            'email' => self::USER_EMAIL,
            'email_verified_at' => $verifiedAt,
            'password' => self::DEFAULT_PASSWORD,
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => $verifiedAt,
            'fiscal_code' => 'UTESCR80A01H501X',
        ]);

        $user->save();
        $user->syncRoles(['user']);

        return $user;
    }

    private function enrollUser(User $user, Course $course, Module $module): void
    {
        $existingEnrollment = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->first();

        if ($existingEnrollment !== null) {
            if ((int) $existingEnrollment->current_module_id !== (int) $module->getKey()) {
                $existingEnrollment->forceFill([
                    'current_module_id' => $module->getKey(),
                ])->save();
            }

            return;
        }

        CourseEnrollment::enroll($user, $course);
    }
}
