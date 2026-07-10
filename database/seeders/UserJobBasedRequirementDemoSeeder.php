<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\JobBasedRequirement;
use App\Models\JobRole;
use App\Models\LanguageLevel;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserJobBasedRequirementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::findOrCreate('user', 'web');
        $jobRole = JobRole::withTrashed()->firstOrCreate(
            ['name' => 'Demo requisito ruolo'],
            ['description' => 'Ruolo usato esclusivamente per verificare la UI dei requisiti.'],
        );
        $jobRole->restore();

        $user = User::withTrashed()->firstOrNew(['email' => 'demo.requisiti@example.test']);
        $user->forceFill([
            'name' => 'Demo',
            'surname' => 'Requisiti',
            'fiscal_code' => 'DMORQS00A01H501X',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'account_state' => 'active',
            'profile_completed_at' => now(),
            'is_foreigner_or_immigrant' => false,
            'job_role_id' => $jobRole->getKey(),
            'requirements_last_calculated_at' => now(),
        ])->save();
        $user->restore();
        $user->syncRoles([$role]);

        $requirement = JobBasedRequirement::withTrashed()->updateOrCreate(
            ['name' => 'Demo - Formazione antincendio'],
            [
                'description' => 'Requisito demo ancora scoperto dall’utente.',
                'is_active' => true,
                'rules' => [[[
                    'field' => 'job_role_id',
                    'operator' => 'IN',
                    'value' => [$jobRole->getKey()],
                ]]],
            ],
        );
        $requirement->restore();

        $languageLevel = LanguageLevel::query()->ordered()->first()
            ?? LanguageLevel::query()->create([
                'name' => 'A1',
                'sort_order' => 1,
                'is_default' => true,
            ]);
        $course = Course::withTrashed()->updateOrCreate(
            ['code' => 'DEMO-REQ-UI1'],
            [
                'title' => 'Demo - Corso requisito antincendio',
                'description' => 'Corso fruibile per verificare la UI dei requisiti utente.',
                'type' => 'async',
                'status' => 'draft',
                'year' => now()->year,
                'expiry_date' => now()->addYear()->endOfDay(),
                'edition' => 1,
                'required_language_level_id' => $languageLevel->getKey(),
                'hasMany' => '0',
                'visible_to_all' => true,
            ],
        );
        $course->restore();
        $course->jobBasedRequirements()->sync([$requirement->getKey()]);

        $quizAppointment = now()->addDay()->startOfDay()->setHour(9);
        $module = Module::withTrashed()->firstOrNew([
            'belongsTo' => (string) $course->getKey(),
            'order' => 1,
        ]);
        $module->forceFill([
            'title' => 'Quiz requisito antincendio',
            'description' => 'Quiz non completato per il corso demo.',
            'type' => Module::TYPE_LEARNING_QUIZ,
            'status' => 'draft',
            'is_live_teacher' => false,
            'appointment_date' => $quizAppointment->toDateTimeString(),
            'appointment_start_time' => $quizAppointment->toDateTimeString(),
            'appointment_end_time' => $quizAppointment->copy()->addMinutes(30)->toDateTimeString(),
            'passing_score' => 1,
            'max_score' => 1,
            'max_attempts' => 3,
            'permitted_submission' => Module::PERMITTED_SUBMISSION_ONLINE,
        ])->save();
        $module->restore();
        $this->syncQuizQuestion($module);
        $module->update(['status' => 'published']);
        $course->update(['status' => 'published']);

        $user->jobBasedRequirements()->syncWithoutDetaching([
            $requirement->getKey() => [
                'is_active' => true,
                'valid_from' => today()->toDateString(),
                'calculated_at' => now(),
            ],
        ]);
        CourseEnrollment::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
            ],
            [
                'status' => CourseEnrollment::STATUS_ASSIGNED,
                'assigned_at' => now(),
                'started_at' => null,
                'completed_at' => null,
                'completion_percentage' => 0,
            ],
        );
    }

    private function syncQuizQuestion(Module $module): void
    {
        $module->quizQuestions()->each(function (ModuleQuizQuestion $question): void {
            $question->answers()->delete();
            $question->delete();
        });

        $question = ModuleQuizQuestion::query()->create([
            'module_id' => $module->getKey(),
            'text' => 'Il corso demo deve risultare non completato?',
            'points' => 1,
        ]);
        $answers = collect([
            'Sì, per mostrare il requisito nella dashboard utente.',
            'No, deve generare subito l’attestato.',
            'Non è rilevante.',
            'Solo se il corso è archiviato.',
        ])->map(fn (string $text): ModuleQuizAnswer => ModuleQuizAnswer::query()->create([
            'question_id' => $question->getKey(),
            'text' => $text,
        ]));

        $question->update(['correct_answer_id' => $answers->first()->getKey()]);
    }
}
