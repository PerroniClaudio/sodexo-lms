<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\JobRole;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\TrainingPath;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TrainingPathApprovalDemoSeeder extends Seeder
{
    private const USER_EMAIL = 'utente-percorso-approvazione-demo@test.com';

    private const USER_FISCAL_CODE = 'UTPAPP80A01H501X';

    private const TRAINING_PATH_CODE = 'DEMO-PERCORSO-APPROVAZIONE';

    public function run(): void
    {
        DB::transaction(function (): void {
            $userRole = $this->upsertJobRole('Demo approvazione - utente');
            $reservedRole = $this->upsertJobRole('Demo approvazione - corso riservato');
            $this->upsertUser($userRole);

            $courses = [
                $this->upsertQuizCourse(
                    code: 'DEMO-CORSO-BASE',
                    title: 'Demo percorso approvazione - Corso base',
                    description: 'Corso idoneo per verificare il normale avanzamento del percorso.',
                ),
                $this->upsertQuizCourse(
                    code: 'DEMO-CORSO-RISERVATO',
                    title: 'Demo percorso approvazione - Corso riservato',
                    description: 'Corso volutamente non assegnabile all\'utente demo.',
                    visibleToAll: false,
                    recipientJobRole: $reservedRole,
                ),
                $this->upsertQuizCourse(
                    code: 'DEMO-CORSO-FINALE',
                    title: 'Demo percorso approvazione - Corso finale',
                    description: 'Corso idoneo successivo a quello da saltare.',
                ),
            ];

            $this->upsertTrainingPath($courses);
        });
    }

    private function upsertJobRole(string $name): JobRole
    {
        return JobRole::query()->firstOrCreate(
            ['name' => $name],
            ['description' => 'Ruolo tecnico per il seeder della conferma iscrizione percorso.'],
        );
    }

    private function upsertUser(JobRole $jobRole): User
    {
        $user = User::query()->firstOrNew(['email' => self::USER_EMAIL]);

        $user->forceFill([
            'name' => 'Utente',
            'surname' => 'Approvazione Demo',
            'email_verified_at' => now(),
            'password' => Hash::make('Sodexo@Test.26'),
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => now(),
            'fiscal_code' => self::USER_FISCAL_CODE,
            'job_role_id' => $jobRole->getKey(),
            'is_foreigner_or_immigrant' => false,
        ])->save();

        $user->syncRoles([Role::findOrCreate('user', 'web')]);

        return $user;
    }

    private function upsertQuizCourse(
        string $code,
        string $title,
        string $description,
        bool $visibleToAll = true,
        ?JobRole $recipientJobRole = null,
    ): Course {
        $course = Course::query()->firstOrNew(['code' => $code]);

        if ($course->exists && $course->status === 'published') {
            return $course;
        }

        $course->fill([
            'title' => $title,
            'description' => $description,
            'type' => 'async',
            'year' => now()->year,
            'expiry_date' => now()->copy()->endOfYear(),
            'status' => 'draft',
            'hasMany' => '1',
            'visible_to_all' => $visibleToAll,
            'has_satisfaction_survey' => false,
            'satisfaction_survey_required_for_certificate' => false,
        ])->save();

        $course->jobRoles()->sync($recipientJobRole?->getKey() !== null
            ? [$recipientJobRole->getKey()]
            : []);

        $this->upsertLearningQuiz($course);

        $course->update(['status' => 'published']);

        return $course;
    }

    private function upsertLearningQuiz(Course $course): void
    {
        $appointment = now()->copy()->setTime(9, 0);
        $module = Module::query()->firstOrNew([
            'belongsTo' => (string) $course->getKey(),
            'order' => 1,
        ]);

        if ($module->exists && $module->status === 'published') {
            $module->update(['status' => 'draft']);
        }

        $module->fill([
            'title' => 'Quiz di apprendimento',
            'description' => 'Quiz demo con domande a risposta multipla.',
            'type' => Module::TYPE_LEARNING_QUIZ,
            'status' => 'draft',
            'is_live_teacher' => false,
            'appointment_date' => $appointment,
            'appointment_start_time' => $appointment,
            'appointment_end_time' => $appointment->copy()->addMinutes(30),
            'passing_score' => 1,
            'max_score' => 2,
            'max_attempts' => 3,
            'permitted_submission' => Module::PERMITTED_SUBMISSION_ONLINE,
        ])->save();

        $this->replaceQuizQuestions($module);

        $module->update(['status' => 'published']);
    }

    private function replaceQuizQuestions(Module $module): void
    {
        $module->quizQuestions()->get()->each(function (ModuleQuizQuestion $question): void {
            $question->forceFill(['correct_answer_id' => null])->save();
            $question->answers()->delete();
            $question->delete();
        });

        foreach ([
            ['Quale azione consente di procedere con un corso non idoneo?', ['Ignorare il blocco', 'Approvare esplicitamente l\'iscrizione', 'Eliminare il percorso', 'Cambiare il quiz']],
            ['Come viene gestito il corso non idoneo dopo l\'approvazione?', ['Viene completato automaticamente', 'Resta assegnato ma viene saltato nel percorso', 'Viene eliminato', 'Diventa un corso diretto']],
        ] as [$text, $answers]) {
            $question = ModuleQuizQuestion::query()->create([
                'module_id' => $module->getKey(),
                'text' => $text,
                'points' => 1,
            ]);

            $answerModels = collect($answers)->map(fn (string $answer): ModuleQuizAnswer => ModuleQuizAnswer::query()->create([
                'question_id' => $question->getKey(),
                'text' => $answer,
            ]));

            $question->update(['correct_answer_id' => $answerModels->get(1)->getKey()]);
        }
    }

    /**
     * @param  array<int, Course>  $courses
     */
    private function upsertTrainingPath(array $courses): void
    {
        $trainingPath = TrainingPath::query()->firstOrNew(['code' => self::TRAINING_PATH_CODE]);
        $trainingPath->fill([
            'title' => 'Demo approvazione iscrizione percorso',
            'description' => 'Percorso con un corso riservato per testare l\'approvazione amministrativa.',
            'status' => 'published',
            'visible_to_all' => true,
            'enforce_course_order' => true,
        ])->save();

        $trainingPath->courses()->sync(
            collect($courses)->mapWithKeys(
                fn (Course $course, int $index): array => [$course->getKey() => ['sort_order' => $index + 1]],
            )->all(),
        );
    }
}
