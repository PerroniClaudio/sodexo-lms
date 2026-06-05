<?php

namespace Database\Seeders;

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\RiskBasedRequirement;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TestCourseCatalogSeeder extends Seeder
{
    private const PREFIX = 'Seeder QA';

    public function run(): void
    {
        DB::transaction(function (): void {
            $video = $this->resolveReadyVideo();
            $requirements = $this->resolveRequirements();

            $this->seedAvailabilityMatrix($video);
            $this->seedRequirementCatalog($requirements, $video);
        });
    }

    public static function availabilityCourseTitle(string $type, string $scenario): string
    {
        return sprintf(
            '%s - %s - %s',
            self::PREFIX,
            strtoupper($type),
            self::availabilityScenarioLabel($scenario),
        );
    }

    public static function requirementCourseTitle(string $type, string $profileCode): string
    {
        return sprintf(
            '%s - %s - %s',
            self::PREFIX,
            strtoupper($type),
            self::requirementProfileLabel($profileCode),
        );
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
                throw new RuntimeException("Requisito rischio mancante: {$name}. Esegui prima RiskBasedRequirementSeeder.");
            }
        }

        return [
            'general' => $requirements->get('Formazione Generale'),
            'low' => $requirements->get('Formazione Specifica Rischio Basso'),
            'medium' => $requirements->get('Formazione Specifica Rischio Medio'),
            'high' => $requirements->get('Formazione Specifica Rischio Alto'),
        ];
    }

    private function resolveReadyVideo(): Video
    {
        $video = Video::query()
            ->where('mux_video_status', 'ready')
            ->orderBy('id')
            ->first();

        if (! $video instanceof Video) {
            throw new RuntimeException('Per eseguire TestCourseCatalogSeeder serve almeno un video in libreria con stato "ready".');
        }

        return $video;
    }

    private function seedAvailabilityMatrix(Video $video): void
    {
        foreach (Course::availableTypes() as $type) {
            foreach ($this->availabilityScenarios() as $scenario => $attributes) {
                $this->upsertCourse(
                    title: self::availabilityCourseTitle($type, $scenario),
                    description: $attributes['description'],
                    type: $type,
                    year: $attributes['year'],
                    expiryDate: $attributes['expiry_date'],
                    status: $attributes['status'],
                    video: $video,
                    requirementPayloads: [],
                );
            }
        }
    }

    /**
     * @param  array<string, RiskBasedRequirement>  $requirements
     */
    private function seedRequirementCatalog(array $requirements, Video $video): void
    {
        foreach (Course::availableTypes() as $type) {
            foreach ($this->requirementProfiles($requirements) as $profileCode => $profile) {
                $this->upsertCourse(
                    title: self::requirementCourseTitle($type, $profileCode),
                    description: $profile['description'],
                    type: $type,
                    year: now()->year,
                    expiryDate: now()->copy()->endOfYear(),
                    status: 'published',
                    video: $video,
                    requirementPayloads: $profile['requirements'],
                );
            }
        }
    }

    /**
     * @param  array<int, array{requirement: RiskBasedRequirement, course_validity_type: CourseRiskRequirementValidityType, integrative_start_risk_levels?: array<int, RiskLevel>}>  $requirementPayloads
     */
    private function upsertCourse(
        string $title,
        string $description,
        string $type,
        int $year,
        Carbon $expiryDate,
        string $status,
        Video $video,
        array $requirementPayloads,
    ): Course {
        $moduleDefinitions = $this->moduleDefinitionsForType($type, $video);
        $course = Course::query()->firstOrNew(['title' => $title]);

        if ($course->exists && $course->status === 'published') {
            $course->update([
                'status' => 'draft',
            ]);
        }

        $course->fill([
            'description' => $description,
            'type' => $type,
            'year' => $year,
            'expiry_date' => $expiryDate->copy()->endOfDay(),
            'status' => 'draft',
            'has_satisfaction_survey' => false,
            'satisfaction_survey_required_for_certificate' => false,
            'hasMany' => (string) count($moduleDefinitions),
        ]);
        $course->save();

        $course->riskBasedRequirements()->sync(
            collect($requirementPayloads)->mapWithKeys(function (array $payload): array {
                $requirement = $payload['requirement'];
                $validityType = $payload['course_validity_type'];
                $integrativeStartLevels = collect($payload['integrative_start_risk_levels'] ?? [])
                    ->map(fn (RiskLevel $riskLevel): string => $riskLevel->value)
                    ->values()
                    ->all();

                return [
                    $requirement->getKey() => [
                        'course_validity_type' => $validityType->value,
                        'integrative_start_risk_levels' => $validityType === CourseRiskRequirementValidityType::Integrative
                            ? json_encode($integrativeStartLevels)
                            : null,
                    ],
                ];
            })->all()
        );

        $this->syncModules($course, $moduleDefinitions);

        if ($course->status !== $status) {
            $course->update([
                'status' => $status,
            ]);
        }

        return $course;
    }

    /**
     * @return array<string, array{year: int, expiry_date: Carbon, status: string, description: string}>
     */
    private function availabilityScenarios(): array
    {
        return [
            'current-published' => [
                'year' => now()->year,
                'expiry_date' => now()->copy()->endOfYear(),
                'status' => 'published',
                'description' => 'Corso demo valido e pubblicato nell\'anno corrente.',
            ],
            'expired-published' => [
                'year' => now()->subYear()->year,
                'expiry_date' => now()->copy()->subYear()->endOfYear(),
                'status' => 'published',
                'description' => 'Corso demo pubblicato ma gia scaduto nell\'anno precedente.',
            ],
            'future-published' => [
                'year' => now()->addYear()->year,
                'expiry_date' => now()->copy()->addYear()->endOfYear(),
                'status' => 'published',
                'description' => 'Corso demo gia pubblicato con validita nel prossimo anno.',
            ],
            'current-unpublished' => [
                'year' => now()->year,
                'expiry_date' => now()->copy()->endOfYear(),
                'status' => 'draft',
                'description' => 'Corso demo valido ma non pubblicato, utile per verificare i filtri di disponibilita.',
            ],
        ];
    }

    /**
     * @param  array<string, RiskBasedRequirement>  $requirements
     * @return array<string, array{
     *     description: string,
     *     requirements: array<int, array{
     *         requirement: RiskBasedRequirement,
     *         course_validity_type: CourseRiskRequirementValidityType,
     *         integrative_start_risk_levels?: array<int, RiskLevel>
     *     }>
     * }>
     */
    private function requirementProfiles(array $requirements): array
    {
        return [
            'req-general-both' => [
                'description' => 'Corso demo associato alla formazione generale, valido sia per primo conseguimento sia per aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['general'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Both,
                    ],
                ],
            ],
            'req-low-both' => [
                'description' => 'Corso demo per rischio basso utilizzabile sia per primo conseguimento sia per aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['low'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Both,
                    ],
                ],
            ],
            'req-low-first' => [
                'description' => 'Corso demo per rischio basso riservato al primo conseguimento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['low'],
                        'course_validity_type' => CourseRiskRequirementValidityType::FirstAchievement,
                    ],
                ],
            ],
            'req-low-refresh' => [
                'description' => 'Corso demo per rischio basso riservato all\'aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['low'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Refresh,
                    ],
                ],
            ],
            'req-medium-both' => [
                'description' => 'Corso demo per rischio medio utilizzabile sia per primo conseguimento sia per aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['medium'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Both,
                    ],
                ],
            ],
            'req-medium-first' => [
                'description' => 'Corso demo per rischio medio riservato al primo conseguimento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['medium'],
                        'course_validity_type' => CourseRiskRequirementValidityType::FirstAchievement,
                    ],
                ],
            ],
            'req-medium-refresh' => [
                'description' => 'Corso demo per rischio medio riservato all\'aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['medium'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Refresh,
                    ],
                ],
            ],
            'req-medium-integrative-from-low' => [
                'description' => 'Corso demo integrativo per passare da rischio basso a rischio medio.',
                'requirements' => [
                    [
                        'requirement' => $requirements['medium'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Integrative,
                        'integrative_start_risk_levels' => [RiskLevel::LOW],
                    ],
                ],
            ],
            'req-high-both' => [
                'description' => 'Corso demo per rischio alto utilizzabile sia per primo conseguimento sia per aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['high'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Both,
                    ],
                ],
            ],
            'req-high-first' => [
                'description' => 'Corso demo per rischio alto riservato al primo conseguimento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['high'],
                        'course_validity_type' => CourseRiskRequirementValidityType::FirstAchievement,
                    ],
                ],
            ],
            'req-high-refresh' => [
                'description' => 'Corso demo per rischio alto riservato all\'aggiornamento.',
                'requirements' => [
                    [
                        'requirement' => $requirements['high'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Refresh,
                    ],
                ],
            ],
            'req-high-integrative-from-low-medium' => [
                'description' => 'Corso demo integrativo per salire a rischio alto partendo da rischio basso o medio.',
                'requirements' => [
                    [
                        'requirement' => $requirements['high'],
                        'course_validity_type' => CourseRiskRequirementValidityType::Integrative,
                        'integrative_start_risk_levels' => [RiskLevel::LOW, RiskLevel::MEDIUM],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function moduleDefinitionsForType(string $courseType, Video $video): array
    {
        $baseAppointment = now()->copy()->startOfDay()->addWeek();

        $videoModule = fn (int $order, string $title): array => [
            'order' => $order,
            'title' => $title,
            'description' => 'Modulo video demo collegato a un video reale di libreria.',
            'type' => Module::TYPE_VIDEO,
            'status' => 'published',
            'is_live_teacher' => false,
            'appointment_date' => $baseAppointment->copy()->addDays($order),
            'appointment_start_time' => $baseAppointment->copy()->addDays($order)->setHour(9),
            'appointment_end_time' => $baseAppointment->copy()->addDays($order)->setHour(10),
            'video_id' => $video->getKey(),
            'passing_score' => null,
            'max_score' => null,
            'max_attempts' => null,
            'permitted_submission' => null,
        ];
        $classroomModule = fn (int $order, string $title): array => [
            'order' => $order,
            'title' => $title,
            'description' => 'Modulo in presenza demo per verificare i corsi residenziali.',
            'type' => Module::TYPE_RESIDENTIAL,
            'status' => 'published',
            'is_live_teacher' => false,
            'appointment_date' => $baseAppointment->copy()->addDays($order),
            'appointment_start_time' => $baseAppointment->copy()->addDays($order)->setHour(9),
            'appointment_end_time' => $baseAppointment->copy()->addDays($order)->setHour(13),
            'video_id' => null,
            'passing_score' => null,
            'max_score' => null,
            'max_attempts' => null,
            'permitted_submission' => null,
        ];
        $liveModule = fn (int $order, string $title): array => [
            'order' => $order,
            'title' => $title,
            'description' => 'Modulo live demo con appuntamento pianificato.',
            'type' => Module::TYPE_LIVE,
            'status' => 'published',
            'is_live_teacher' => true,
            'appointment_date' => $baseAppointment->copy()->addDays($order),
            'appointment_start_time' => $baseAppointment->copy()->addDays($order)->setHour(15),
            'appointment_end_time' => $baseAppointment->copy()->addDays($order)->setHour(17),
            'video_id' => null,
            'passing_score' => null,
            'max_score' => null,
            'max_attempts' => null,
            'permitted_submission' => null,
        ];
        $quizModule = fn (int $order): array => [
            'order' => $order,
            'title' => 'Quiz finale di apprendimento',
            'description' => 'Quiz demo minimo con due domande e quattro risposte ciascuna.',
            'type' => Module::TYPE_LEARNING_QUIZ,
            'status' => 'published',
            'is_live_teacher' => false,
            'appointment_date' => $baseAppointment->copy()->addDays($order),
            'appointment_start_time' => $baseAppointment->copy()->addDays($order)->setHour(18),
            'appointment_end_time' => $baseAppointment->copy()->addDays($order)->setHour(18)->addMinutes(20),
            'video_id' => null,
            'passing_score' => 1,
            'max_score' => 2,
            'max_attempts' => 3,
            'permitted_submission' => Module::PERMITTED_SUBMISSION_ONLINE,
        ];

        return match ($courseType) {
            'res' => [
                $classroomModule(1, 'Modulo RES demo'),
                $quizModule(2),
            ],
            'blended' => [
                $videoModule(1, 'Modulo video introduttivo'),
                $classroomModule(2, 'Modulo RES di approfondimento'),
                $quizModule(3),
            ],
            'fsc' => [
                $liveModule(1, 'Modulo FSC sul campo'),
                $quizModule(2),
            ],
            'fad', 'async' => [
                $videoModule(1, 'Modulo video demo'),
                $quizModule(2),
            ],
            default => [
                $videoModule(1, 'Modulo video demo'),
                $quizModule(2),
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $moduleDefinitions
     */
    private function syncModules(Course $course, array $moduleDefinitions): void
    {
        $expectedOrders = collect($moduleDefinitions)->pluck('order')->all();

        $course->modules()
            ->whereNotIn('order', $expectedOrders)
            ->get()
            ->each(function (Module $module): void {
                $module->quizQuestions()->get()->each(function (ModuleQuizQuestion $question): void {
                    $question->answers()->delete();
                    $question->delete();
                });
                $module->delete();
            });

        foreach ($moduleDefinitions as $definition) {
            $module = Module::query()->firstOrNew(
                [
                    'belongsTo' => (string) $course->getKey(),
                    'order' => $definition['order'],
                ],
            );

            if ($module->exists && $module->status === 'published') {
                $module->update([
                    'status' => 'draft',
                ]);
            }

            $module->fill([
                'title' => $definition['title'],
                'description' => $definition['description'],
                'type' => $definition['type'],
                'is_live_teacher' => $definition['is_live_teacher'],
                'appointment_date' => $definition['appointment_date'],
                'appointment_start_time' => $definition['appointment_start_time'],
                'appointment_end_time' => $definition['appointment_end_time'],
                'status' => 'draft',
                'video_id' => $definition['video_id'],
                'passing_score' => $definition['passing_score'],
                'max_score' => $definition['max_score'],
                'max_attempts' => $definition['max_attempts'],
                'permitted_submission' => $definition['permitted_submission'],
            ]);
            $module->save();

            if ($module->type === Module::TYPE_LEARNING_QUIZ) {
                $this->syncQuizQuestions($module, $course);
            }

            if ($module->status !== $definition['status']) {
                $module->update([
                    'status' => $definition['status'],
                ]);
            }
        }
    }

    private function syncQuizQuestions(Module $module, Course $course): void
    {
        $module->quizQuestions()->get()->each(function (ModuleQuizQuestion $question): void {
            $question->answers()->delete();
            $question->delete();
        });

        foreach ($this->quizDataset($course) as $questionIndex => $questionData) {
            $question = ModuleQuizQuestion::query()->create([
                'module_id' => $module->getKey(),
                'text' => sprintf('%d. %s', $questionIndex + 1, $questionData['text']),
                'points' => 1,
                'correct_answer_id' => null,
            ]);

            $correctAnswerId = null;

            foreach ($questionData['answers'] as $answerIndex => $answerText) {
                $answer = ModuleQuizAnswer::query()->create([
                    'question_id' => $question->getKey(),
                    'text' => $answerText,
                ]);

                if ($answerIndex === $questionData['correct']) {
                    $correctAnswerId = $answer->getKey();
                }
            }

            $question->forceFill([
                'correct_answer_id' => $correctAnswerId,
            ])->save();
        }
    }

    /**
     * @return array<int, array{text: string, answers: array<int, string>, correct: int}>
     */
    private function quizDataset(Course $course): array
    {
        return [
            [
                'text' => "Questo quiz verifica il completamento del corso demo {$course->title}?",
                'answers' => [
                    'Si, serve come verifica minima di apprendimento.',
                    'No, sostituisce la libreria video.',
                    'No, viene usato solo per cancellare le iscrizioni.',
                    'Si, ma solo per i corsi archiviati.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Quante risposte deve avere ogni domanda del quiz demo?',
                'answers' => [
                    'Due',
                    'Tre',
                    'Quattro',
                    'Cinque',
                ],
                'correct' => 2,
            ],
        ];
    }

    private static function availabilityScenarioLabel(string $scenario): string
    {
        return match ($scenario) {
            'current-published' => 'Catalogo corrente pubblicato',
            'expired-published' => 'Catalogo scaduto pubblicato',
            'future-published' => 'Catalogo futuro pubblicato',
            'current-unpublished' => 'Catalogo corrente non pubblicato',
            default => $scenario,
        };
    }

    private static function requirementProfileLabel(string $profileCode): string
    {
        return match ($profileCode) {
            'req-general-both' => 'Requisito generale both',
            'req-low-both' => 'Requisito rischio basso both',
            'req-low-first' => 'Requisito rischio basso first achievement',
            'req-low-refresh' => 'Requisito rischio basso refresh',
            'req-medium-both' => 'Requisito rischio medio both',
            'req-medium-first' => 'Requisito rischio medio first achievement',
            'req-medium-refresh' => 'Requisito rischio medio refresh',
            'req-medium-integrative-from-low' => 'Requisito rischio medio integrative da basso',
            'req-high-both' => 'Requisito rischio alto both',
            'req-high-first' => 'Requisito rischio alto first achievement',
            'req-high-refresh' => 'Requisito rischio alto refresh',
            'req-high-integrative-from-low-medium' => 'Requisito rischio alto integrative da basso o medio',
            default => $profileCode,
        };
    }
}
