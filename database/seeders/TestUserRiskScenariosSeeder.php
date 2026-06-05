<?php

namespace Database\Seeders;

use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\RiskBasedRequirement;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestUserRiskScenariosSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $fixtures = $this->seedRiskFixtures();
            $requirements = $this->resolveRequirements();
            $courses = $this->resolveRequirementCourses();

            $this->seedUserScenarios($fixtures, $requirements, $courses);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function seedRiskFixtures(): array
    {
        $jobUnit = JobUnit::query()->orderBy('id')->first();
        $jobRole = JobRole::query()->where('name', 'Lavoratore')->first() ?? JobRole::query()->orderBy('id')->first();
        $jobCategory = JobCategory::query()->orderBy('id')->first();
        $jobLevel = JobLevel::query()->orderBy('id')->first();

        if (! $jobUnit || ! $jobRole) {
            throw new RuntimeException('Dati job mancanti: esegui DatabaseSeeder prima di TestDataSeeder.');
        }

        $sectorLow = JobSector::query()->updateOrCreate(
            ['name' => 'Seeder QA - Settore manuale basso'],
            ['description' => 'Settore demo con rischio manuale basso.', 'manual_risk_level' => RiskLevel::LOW],
        );
        $sectorMedium = JobSector::query()->updateOrCreate(
            ['name' => 'Seeder QA - Settore manuale medio'],
            ['description' => 'Settore demo con rischio manuale medio.', 'manual_risk_level' => RiskLevel::MEDIUM],
        );
        $sectorHigh = JobSector::query()->updateOrCreate(
            ['name' => 'Seeder QA - Settore manuale alto'],
            ['description' => 'Settore demo con rischio manuale alto.', 'manual_risk_level' => RiskLevel::HIGH],
        );

        $taskLowOverride = JobTask::query()->updateOrCreate(
            ['code' => 'QA_RISK_LOW_OVERRIDE'],
            [
                'name' => 'Seeder QA - Mansione rischio basso override',
                'description' => 'Mansione demo che forza rischio basso con override.',
                'global_risk_level' => null,
                'global_sector_risk_override' => false,
            ],
        );
        $taskMediumOverride = JobTask::query()->updateOrCreate(
            ['code' => 'QA_RISK_MEDIUM_OVERRIDE'],
            [
                'name' => 'Seeder QA - Mansione rischio medio override',
                'description' => 'Mansione demo che forza rischio medio con override.',
                'global_risk_level' => null,
                'global_sector_risk_override' => false,
            ],
        );
        $taskHighOverride = JobTask::query()->updateOrCreate(
            ['code' => 'QA_RISK_HIGH_OVERRIDE'],
            [
                'name' => 'Seeder QA - Mansione rischio alto override',
                'description' => 'Mansione demo che forza rischio alto con override.',
                'global_risk_level' => null,
                'global_sector_risk_override' => false,
            ],
        );
        $taskLowNoOverride = JobTask::query()->updateOrCreate(
            ['code' => 'QA_RISK_LOW_NO_OVERRIDE'],
            [
                'name' => 'Seeder QA - Mansione rischio basso senza override',
                'description' => 'Mansione demo che non puo abbassare il rischio nativo del settore.',
                'global_risk_level' => null,
                'global_sector_risk_override' => false,
            ],
        );
        $taskGlobalHighOverride = JobTask::query()->updateOrCreate(
            ['code' => 'QA_RISK_HIGH_GLOBAL_OVERRIDE'],
            [
                'name' => 'Seeder QA - Mansione rischio alto globale override',
                'description' => 'Mansione demo che usa i campi globali di rischio.',
                'global_risk_level' => RiskLevel::HIGH,
                'global_sector_risk_override' => true,
            ],
        );

        $this->syncSectorTaskRisk($sectorLow, $taskLowOverride, RiskLevel::LOW, true);
        $this->syncSectorTaskRisk($sectorLow, $taskMediumOverride, RiskLevel::MEDIUM, true);
        $this->syncSectorTaskRisk($sectorLow, $taskHighOverride, RiskLevel::HIGH, true);
        $this->syncSectorTaskRisk($sectorMedium, $taskLowNoOverride, RiskLevel::LOW, false);
        $this->syncSectorTaskRisk($sectorHigh, $taskMediumOverride, RiskLevel::MEDIUM, false);

        return [
            'jobUnit' => $jobUnit,
            'jobRole' => $jobRole,
            'jobCategory' => $jobCategory,
            'jobLevel' => $jobLevel,
            'sectorLow' => $sectorLow,
            'sectorMedium' => $sectorMedium,
            'sectorHigh' => $sectorHigh,
            'taskLowOverride' => $taskLowOverride,
            'taskMediumOverride' => $taskMediumOverride,
            'taskHighOverride' => $taskHighOverride,
            'taskLowNoOverride' => $taskLowNoOverride,
            'taskGlobalHighOverride' => $taskGlobalHighOverride,
        ];
    }

    private function syncSectorTaskRisk(JobSector $sector, JobTask $task, RiskLevel $riskLevel, bool $override): void
    {
        $sector->jobTasks()->syncWithoutDetaching([
            $task->getKey() => [
                'task_risk_level' => $riskLevel->value,
                'sector_risk_override' => $override,
            ],
        ]);
    }

    /**
     * @return array<string, RiskBasedRequirement>
     */
    private function resolveRequirements(): array
    {
        $requirements = RiskBasedRequirement::query()
            ->whereIn('name', [
                'Formazione Generale',
                'Formazione Specifica Rischio Basso',
                'Formazione Specifica Rischio Medio',
                'Formazione Specifica Rischio Alto',
            ])
            ->get()
            ->keyBy('name');

        foreach ([
            'Formazione Generale',
            'Formazione Specifica Rischio Basso',
            'Formazione Specifica Rischio Medio',
            'Formazione Specifica Rischio Alto',
        ] as $name) {
            if (! $requirements->has($name)) {
                throw new RuntimeException("Requisito rischio mancante: {$name}.");
            }
        }

        return [
            'general' => $requirements->get('Formazione Generale'),
            'low' => $requirements->get('Formazione Specifica Rischio Basso'),
            'medium' => $requirements->get('Formazione Specifica Rischio Medio'),
            'high' => $requirements->get('Formazione Specifica Rischio Alto'),
        ];
    }

    /**
     * @return array<string, Course>
     */
    private function resolveRequirementCourses(): array
    {
        $titles = [
            'general' => TestCourseCatalogSeeder::requirementCourseTitle('fad', 'req-general-both'),
            'low' => TestCourseCatalogSeeder::requirementCourseTitle('fad', 'req-low-both'),
            'medium' => TestCourseCatalogSeeder::requirementCourseTitle('fad', 'req-medium-both'),
            'high' => TestCourseCatalogSeeder::requirementCourseTitle('fad', 'req-high-both'),
        ];

        return collect($titles)->mapWithKeys(function (string $title, string $key): array {
            $course = Course::query()->where('title', $title)->first();

            if (! $course instanceof Course) {
                throw new RuntimeException("Corso demo mancante: {$title}. Esegui prima TestCourseCatalogSeeder.");
            }

            return [$key => $course];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $fixtures
     * @param  array<string, RiskBasedRequirement>  $requirements
     * @param  array<string, Course>  $courses
     */
    private function seedUserScenarios(array $fixtures, array $requirements, array $courses): void
    {
        $users = [
            [
                'email' => 'qa-low-no-cert@test.com',
                'name' => 'Scenario',
                'surname' => 'Low No Cert',
                'fiscal_code' => 'QALOW80A01H501A',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskLowOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskLowOverride'], 'starts_at' => now()->subMonths(12), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio basso senza attestati.',
                'certificates' => [],
            ],
            [
                'email' => 'qa-medium-no-cert@test.com',
                'name' => 'Scenario',
                'surname' => 'Medium No Cert',
                'fiscal_code' => 'QAMED80A01H501B',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskMediumOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskMediumOverride'], 'starts_at' => now()->subMonths(12), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio medio senza attestati.',
                'certificates' => [],
            ],
            [
                'email' => 'qa-high-no-cert@test.com',
                'name' => 'Scenario',
                'surname' => 'High No Cert',
                'fiscal_code' => 'QAHIGH80A01H501C',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskHighOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskHighOverride'], 'starts_at' => now()->subMonths(12), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio alto senza attestati.',
                'certificates' => [],
            ],
            [
                'email' => 'qa-low-valid-cert@test.com',
                'name' => 'Scenario',
                'surname' => 'Low Valid Cert',
                'fiscal_code' => 'QALVCF80A01H501D',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskLowOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskLowOverride'], 'starts_at' => now()->subMonths(24), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio basso e attestati validi generale e specifico basso.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(3), 'expires_at' => null],
                    ['requirement' => $requirements['low'], 'course' => $courses['low'], 'issued_at' => now()->subYears(2), 'expires_at' => now()->addYears(3)],
                ],
            ],
            [
                'email' => 'qa-low-expired-refresh@test.com',
                'name' => 'Scenario',
                'surname' => 'Low Expired Refresh',
                'fiscal_code' => 'QALEXR80A01H501E',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskLowOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskLowOverride'], 'starts_at' => now()->subYears(6), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio basso e attestato specifico scaduto ma ancora in finestra aggiornamento.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(8), 'expires_at' => null],
                    ['requirement' => $requirements['low'], 'course' => $courses['low'], 'issued_at' => now()->subYears(5), 'expires_at' => now()->subMonths(2)],
                ],
            ],
            [
                'email' => 'qa-low-expired-reset@test.com',
                'name' => 'Scenario',
                'surname' => 'Low Expired Reset',
                'fiscal_code' => 'QALEXS80A01H501F',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskLowOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskLowOverride'], 'starts_at' => now()->subYears(16), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio basso e attestato scaduto oltre la finestra di reset.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(16), 'expires_at' => null],
                    ['requirement' => $requirements['low'], 'course' => $courses['low'], 'issued_at' => now()->subYears(16), 'expires_at' => now()->subYears(11)],
                ],
            ],
            [
                'email' => 'qa-medium-covered-by-high@test.com',
                'name' => 'Scenario',
                'surname' => 'Medium Covered High',
                'fiscal_code' => 'QAMCHG80A01H501G',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskMediumOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskMediumOverride'], 'starts_at' => now()->subYears(2), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio medio coperto da attestato valido di rischio alto.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(4), 'expires_at' => null],
                    ['requirement' => $requirements['high'], 'course' => $courses['high'], 'issued_at' => now()->subYears(1), 'expires_at' => now()->addYears(4)],
                ],
            ],
            [
                'email' => 'qa-high-with-medium-cert@test.com',
                'name' => 'Scenario',
                'surname' => 'High With Medium Cert',
                'fiscal_code' => 'QAHWMC80A01H501H',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskHighOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskHighOverride'], 'starts_at' => now()->subYears(1), 'ends_at' => null],
                ],
                'notes' => 'Utente con rischio alto ma attestato valido solo di rischio medio.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(3), 'expires_at' => null],
                    ['requirement' => $requirements['medium'], 'course' => $courses['medium'], 'issued_at' => now()->subYears(1), 'expires_at' => now()->addYears(4)],
                ],
            ],
            [
                'email' => 'qa-future-low-to-high@test.com',
                'name' => 'Scenario',
                'surname' => 'Future Low High',
                'fiscal_code' => 'QAFLTH80A01H501I',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskLowOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskLowOverride'], 'starts_at' => now()->subMonths(18), 'ends_at' => now()->addMonth()],
                    ['task' => $fixtures['taskHighOverride'], 'starts_at' => now()->addMonth(), 'ends_at' => null],
                ],
                'notes' => 'Utente con variazione futura entro 3 mesi da rischio basso a rischio alto.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(2), 'expires_at' => null],
                    ['requirement' => $requirements['low'], 'course' => $courses['low'], 'issued_at' => now()->subYears(1), 'expires_at' => now()->addYears(4)],
                ],
            ],
            [
                'email' => 'qa-future-high-to-medium@test.com',
                'name' => 'Scenario',
                'surname' => 'Future High Medium',
                'fiscal_code' => 'QAFHTM80A01H501L',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskHighOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskHighOverride'], 'starts_at' => now()->subMonths(18), 'ends_at' => now()->addMonths(2)],
                    ['task' => $fixtures['taskMediumOverride'], 'starts_at' => now()->addMonths(2), 'ends_at' => null],
                ],
                'notes' => 'Utente con variazione futura entro 3 mesi da rischio alto a rischio medio.',
                'certificates' => [
                    ['requirement' => $requirements['general'], 'course' => $courses['general'], 'issued_at' => now()->subYears(4), 'expires_at' => null],
                    ['requirement' => $requirements['high'], 'course' => $courses['high'], 'issued_at' => now()->subYears(2), 'expires_at' => now()->addYears(3)],
                ],
            ],
            [
                'email' => 'qa-sector-medium-no-override@test.com',
                'name' => 'Scenario',
                'surname' => 'Sector Medium Native',
                'fiscal_code' => 'QASMNO80A01H501M',
                'sector' => $fixtures['sectorMedium'],
                'primary_task' => $fixtures['taskLowNoOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskLowNoOverride'], 'starts_at' => now()->subMonths(10), 'ends_at' => null],
                ],
                'notes' => 'Utente con settore medio e mansione bassa senza override: il rischio effettivo resta medio.',
                'certificates' => [],
            ],
            [
                'email' => 'qa-global-high-override@test.com',
                'name' => 'Scenario',
                'surname' => 'Global High Override',
                'fiscal_code' => 'QAGHOV80A01H501N',
                'sector' => $fixtures['sectorLow'],
                'primary_task' => $fixtures['taskGlobalHighOverride'],
                'tasks' => [
                    ['task' => $fixtures['taskGlobalHighOverride'], 'starts_at' => now()->subMonths(8), 'ends_at' => null],
                ],
                'notes' => 'Utente con mansione che usa global_risk_level e global_sector_risk_override.',
                'certificates' => [],
            ],
        ];

        foreach ($users as $index => $userData) {
            $user = $this->upsertScenarioUser($userData, $fixtures, $index);
            $this->syncUserTasks($user, $userData['tasks']);
            $this->syncUserCertificates($user, $userData['certificates']);
        }
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $fixtures
     */
    private function upsertScenarioUser(array $userData, array $fixtures, int $index): User
    {
        $jobUnit = $fixtures['jobUnit'];
        $jobRole = $fixtures['jobRole'];
        $jobCategory = $fixtures['jobCategory'];
        $jobLevel = $fixtures['jobLevel'];
        $sector = $userData['sector'];
        $primaryTask = $userData['primary_task'];

        $user = User::query()->firstOrNew([
            'email' => $userData['email'],
        ]);

        $user->forceFill([
            'name' => $userData['name'],
            'surname' => $userData['surname'],
            'email_verified_at' => now(),
            'password' => 'Sodexo@Test.26',
            'account_state' => UserStatus::ACTIVE,
            'profile_completed_at' => now(),
            'fiscal_code' => $userData['fiscal_code'],
            'birth_date' => now()->subYears(30 + $index)->toDateString(),
            'employment_start_date' => now()->subYears(5)->toDateString(),
            'employment_end_date' => null,
            'birth_place' => 'Milano',
            'gender' => $index % 2 === 0 ? 'M' : 'F',
            'phone_prefix' => '+39',
            'phone' => sprintf('320000%04d', $index + 1),
            'home_country_id' => $jobUnit->country_id,
            'home_region_id' => $jobUnit->region_id,
            'home_province_id' => $jobUnit->province_id,
            'home_city_id' => $jobUnit->city_id,
            'address' => 'Via Seeder QA '.($index + 1),
            'postal_code' => $jobUnit->postal_code,
            'job_unit_id' => $jobUnit->getKey(),
            'job_category_id' => $jobCategory?->getKey(),
            'job_level_id' => $jobLevel?->getKey(),
            'job_task_id' => $primaryTask->getKey(),
            'job_role_id' => $jobRole->getKey(),
            'job_sector_id' => $sector->getKey(),
            'is_foreigner_or_immigrant' => false,
            'notes' => $userData['notes'],
        ]);

        $user->save();
        $user->syncRoles(['user']);

        return $user;
    }

    /**
     * @param  array<int, array{task: JobTask, starts_at: Carbon, ends_at: ?Carbon}>  $taskPayloads
     */
    private function syncUserTasks(User $user, array $taskPayloads): void
    {
        $user->jobTasks()->detach();

        foreach ($taskPayloads as $taskPayload) {
            $user->jobTasks()->attach($taskPayload['task']->getKey(), [
                'starts_at' => $taskPayload['starts_at']->toDateString(),
                'ends_at' => $taskPayload['ends_at']?->toDateString(),
            ]);
        }
    }

    /**
     * @param  array<int, array{requirement: RiskBasedRequirement, course: ?Course, issued_at: Carbon, expires_at: ?Carbon}>  $certificatePayloads
     */
    private function syncUserCertificates(User $user, array $certificatePayloads): void
    {
        $user->userCertificates()->get()->each(function (UserCertificate $certificate): void {
            $certificate->riskBasedRequirements()->detach();
            $certificate->delete();
        });

        foreach ($certificatePayloads as $certificatePayload) {
            $course = $certificatePayload['course'];
            $certificate = $user->userCertificates()->create([
                'internal_course_id' => $course?->getKey(),
                'name' => $course?->title ?? 'Seeder QA - Certificato esterno',
                'description' => 'Certificato demo per lo scenario '.$user->email,
                'is_internal' => $course !== null,
                'issued_at' => $certificatePayload['issued_at']->toDateString(),
                'expires_at' => $certificatePayload['expires_at']?->toDateString(),
            ]);

            $certificate->riskBasedRequirements()->sync([
                $certificatePayload['requirement']->getKey(),
            ]);
        }
    }
}
