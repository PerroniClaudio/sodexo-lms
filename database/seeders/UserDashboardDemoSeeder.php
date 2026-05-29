<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Models\Video;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserDashboardDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const USER_EMAIL = 'user@test.com';

    private const DEFAULT_PASSWORD = 'Sodexo@Test.26';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = $this->upsertUser();

        $courseScenarios = [
            [
                'title' => 'Percorso onboarding HACCP',
                'description' => 'Percorso introduttivo completato.',
                'completed_modules' => 3,
                'hours' => [1.2, 0.8, 0.5],
                'days_ago' => [6, 5, 4],
            ],
            [
                'title' => 'Aggiornamento sicurezza reparto',
                'description' => 'Percorso quasi completato con ultimo modulo ancora disponibile.',
                'completed_modules' => 2,
                'hours' => [0.9, 1.1],
                'days_ago' => [3, 1],
            ],
            [
                'title' => 'Privacy e trattamento dati',
                'description' => 'Percorso avviato di recente.',
                'completed_modules' => 1,
                'hours' => [0.75],
                'days_ago' => [0],
            ],
            [
                'title' => 'Benessere e cultura aziendale',
                'description' => 'Percorso assegnato ma non ancora iniziato.',
                'completed_modules' => 0,
                'hours' => [],
                'days_ago' => [],
            ],
        ];

        foreach ($courseScenarios as $index => $scenario) {
            $course = $this->upsertCourse($scenario['title'], $scenario['description'], $index + 1);
            $modules = $this->upsertVideoModules($course);
            $enrollment = $this->enrollUser($user, $course);

            $this->syncModuleProgressRecords($enrollment, $modules);
            $this->applyScenarioProgress(
                $enrollment,
                $modules,
                $scenario['completed_modules'],
                $scenario['hours'],
                $scenario['days_ago'],
            );
        }
    }

    private function upsertUser(): User
    {
        $verifiedAt = now();

        $user = User::query()->firstOrNew([
            'email' => self::USER_EMAIL,
        ]);

        $user->forceFill([
            'name' => 'User',
            'surname' => 'Demo',
            'email_verified_at' => $verifiedAt,
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => $verifiedAt,
            'fiscal_code' => 'USERDM80A01H501Z',
            'is_foreigner_or_immigrant' => false,
        ]);

        $user->save();
        $user->syncRoles([Role::findOrCreate('user')]);

        return $user;
    }

    private function upsertCourse(string $title, string $description, int $sequence): Course
    {
        return Course::query()->updateOrCreate(
            ['title' => $title],
            [
                'description' => $description,
                'type' => 'async',
                'year' => (int) now()->year,
                'expiry_date' => now()->addMonths(8 + $sequence),
                'status' => 'published',
                'hasMany' => '3',
            ]
        );
    }

    /**
     * @return array<int, Module>
     */
    private function upsertVideoModules(Course $course): array
    {
        $moduleBlueprints = [
            1 => ['title' => 'Introduzione', 'minutes' => 18],
            2 => ['title' => 'Contenuti operativi', 'minutes' => 26],
            3 => ['title' => 'Verifica finale', 'minutes' => 14],
        ];

        $modules = [];

        foreach ($moduleBlueprints as $order => $blueprint) {
            $appointmentStart = now()->addDays($order)->setTime(9 + $order, 0);
            $video = Video::query()->firstOrCreate(
                ['title' => sprintf('%s - %s', $course->title, $blueprint['title'])],
                [
                    'description' => sprintf('Video demo per %s.', $blueprint['title']),
                    'mux_video_status' => 'ready',
                    'duration_seconds' => $blueprint['minutes'] * 60,
                ]
            );

            $modules[] = Module::query()->updateOrCreate(
                [
                    'belongsTo' => (string) $course->getKey(),
                    'order' => $order,
                ],
                [
                    'title' => $blueprint['title'],
                    'description' => sprintf('Modulo demo %d per dashboard utente.', $order),
                    'type' => Module::TYPE_VIDEO,
                    'is_live_teacher' => false,
                    'appointment_date' => $appointmentStart,
                    'appointment_start_time' => $appointmentStart,
                    'appointment_end_time' => $appointmentStart->copy()->addMinutes($blueprint['minutes']),
                    'status' => 'published',
                    'passing_score' => null,
                    'max_score' => null,
                    'max_attempts' => null,
                    'permitted_submission' => null,
                    'video_id' => $video->getKey(),
                ]
            );

            $modules[array_key_last($modules)]->setRelation('video', $video);
        }

        return $modules;
    }

    private function enrollUser(User $user, Course $course): CourseEnrollment
    {
        $enrollment = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('course_id', $course->getKey())
            ->whereNull('deleted_at')
            ->first();

        if ($enrollment !== null) {
            return $enrollment;
        }

        return CourseEnrollment::enroll($user, $course);
    }

    /**
     * @param  array<int, Module>  $modules
     */
    private function syncModuleProgressRecords(CourseEnrollment $enrollment, array $modules): void
    {
        foreach ($modules as $index => $module) {
            $enrollment->moduleProgresses()->firstOrCreate(
                ['module_id' => $module->getKey()],
                [
                    'status' => $index === 0
                        ? ModuleProgress::STATUS_AVAILABLE
                        : ModuleProgress::STATUS_LOCKED,
                ]
            );
        }
    }

    /**
     * @param  array<int, Module>  $modules
     * @param  array<int, float>  $hours
     * @param  array<int, int>  $daysAgo
     */
    private function applyScenarioProgress(
        CourseEnrollment $enrollment,
        array $modules,
        int $completedModules,
        array $hours,
        array $daysAgo,
    ): void {
        foreach ($modules as $index => $module) {
            $progress = $enrollment->moduleProgresses()
                ->where('module_id', $module->getKey())
                ->firstOrFail();

            $isCompleted = $index < $completedModules;
            $activityDate = isset($daysAgo[$index])
                ? CarbonImmutable::now()->subDays($daysAgo[$index])->setTime(10 + $index, 15)
                : null;
            $timeSpentSeconds = (int) round(($hours[$index] ?? 0) * 3600);

            if ($isCompleted) {
                $progress->forceFill([
                    'status' => ModuleProgress::STATUS_COMPLETED,
                    'started_at' => $activityDate?->subMinutes(45) ?? now()->subDays(2),
                    'completed_at' => $activityDate,
                    'last_accessed_at' => $activityDate,
                    'time_spent_seconds' => $timeSpentSeconds,
                    'video_current_second' => $module->video?->duration_seconds,
                    'video_max_second' => $module->video?->duration_seconds,
                    'passed_at' => $activityDate,
                ])->save();

                continue;
            }

            $progress->forceFill([
                'status' => $index === $completedModules
                    ? ModuleProgress::STATUS_AVAILABLE
                    : ModuleProgress::STATUS_LOCKED,
                'started_at' => null,
                'completed_at' => null,
                'last_accessed_at' => null,
                'time_spent_seconds' => 0,
                'video_current_second' => null,
                'video_max_second' => null,
                'passed_at' => null,
            ])->save();
        }

        $enrollment->refreshCurrentModulePointer();
        $enrollment->syncProgressState();
    }
}
