<?php

namespace App\Models;

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Pivots\CourseRiskBasedRequirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    public const DISALLOWED_MODULE_TYPES_BY_COURSE_TYPE = [
        'fad' => [
            Module::TYPE_VIDEO,
            Module::TYPE_RESIDENTIAL,
            Module::TYPE_SCORM,
        ],
        'res' => [
            Module::TYPE_VIDEO,
            Module::TYPE_LIVE,
            Module::TYPE_SCORM,
        ],
        'blended' => [],
        'fsc' => [
            Module::TYPE_VIDEO,
            Module::TYPE_LIVE,
            Module::TYPE_SCORM,
        ],
        'async' => [
            Module::TYPE_LIVE,
            Module::TYPE_RESIDENTIAL,
        ],
    ];

    public const AUDIT_TRAIL_TYPES = [
        'fad',
        'async',
    ];

    public const TYPES = [
        'fad',
        'res',
        'blended',
        'fsc',
        'async',
    ];

    public const STATUSES = [
        'draft',
        'published',
        'archived',
    ];

    public const EVENT_TYPES = [
        'aggiornamento',
        'formazione obbligatoria',
        'addestramento',
        'corso normativo',
    ];

    public const PARTICIPANT_PRESENCE_VERIFICATIONS = [
        'signature',
        'badge_qr',
        'other',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'code',
        'description',
        'cover_image_path',
        'poster_pdf_path',
        'teaching_material',
        'max_participants',
        'participant_presence_verification',
        'internal_notes',
        'training_objective',
        'knowledge',
        'skills',
        'competences',
        'regulatory_reference',
        'course_start_date',
        'course_end_date',
        'access_closure_date',
        'course_duration_hours',
        'interaction_duration_minutes',
        'program_schedule',
        'type',
        'event_type',
        'year',
        'expiry_date',
        'status',
        'required_language_level_id',
        'is_language_verification_course',
        'grants_language_level_id',
        'is_financed',
        'funding_entity_id',
        'job_unit_id',
        'venue_id',
        'edition',
        'original_course_id',
        'has_satisfaction_survey',
        'satisfaction_survey_required_for_certificate',
        'hasMany',
        'visible_to_all',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'expiry_date' => 'datetime',
            'max_participants' => 'integer',
            'is_financed' => 'boolean',
            'funding_entity_id' => 'integer',
            'job_unit_id' => 'integer',
            'venue_id' => 'integer',
            'edition' => 'integer',
            'original_course_id' => 'integer',
            'required_language_level_id' => 'integer',
            'is_language_verification_course' => 'boolean',
            'grants_language_level_id' => 'integer',
            'course_start_date' => 'date',
            'course_end_date' => 'date',
            'access_closure_date' => 'date',
            'course_duration_hours' => 'integer',
            'interaction_duration_minutes' => 'integer',
            'program_schedule' => 'array',
            'has_satisfaction_survey' => 'boolean',
            'satisfaction_survey_required_for_certificate' => 'boolean',
            'visible_to_all' => 'boolean',
        ];
    }

    /**
     * Get the available course types.
     *
     * @return array<int, string>
     */
    public static function availableTypes(): array
    {
        return self::TYPES;
    }

    /**
     * Get the translated labels for the available course types.
     *
     * @return array<string, string>
     */
    public static function availableTypeLabels(): array
    {
        return [
            'fad' => __('FAD'),
            'res' => __('RES'),
            'blended' => __('BLENDED'),
            'fsc' => __('FSC'),
            'async' => __('FAD Asincrona'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function disallowedModuleTypes(): array
    {
        return self::DISALLOWED_MODULE_TYPES_BY_COURSE_TYPE[$this->type] ?? [];
    }

    public function allowsModuleType(string $moduleType): bool
    {
        return ! in_array($moduleType, $this->disallowedModuleTypes(), true);
    }

    public function moduleTypeRestrictionMessage(string $moduleType): ?string
    {
        if ($this->allowsModuleType($moduleType)) {
            return null;
        }

        $courseTypeLabel = self::availableTypeLabels()[$this->type] ?? $this->type;
        $moduleTypeLabel = Module::availableTypeLabels()[$moduleType] ?? $moduleType;

        return __('modules.messages.restricted_type', [
            'course_type' => $courseTypeLabel,
            'module_type' => $moduleTypeLabel,
        ]);
    }

    /**
     * Get the available course statuses.
     *
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return self::STATUSES;
    }

    /**
     * Get the translated labels for the available course statuses.
     *
     * @return array<string, string>
     */
    public static function availableStatusLabels(): array
    {
        return [
            'draft' => __('Bozza'),
            'published' => __('Pubblicato'),
            'archived' => __('Archiviato'),
        ];
    }

    /**
     * Get the available event types.
     *
     * @return array<int, string>
     */
    public static function availableEventTypes(): array
    {
        return self::EVENT_TYPES;
    }

    /**
     * Get the translated labels for the available event types.
     *
     * @return array<string, string>
     */
    public static function availableEventTypeLabels(): array
    {
        return [
            'aggiornamento' => __('Aggiornamento'),
            'formazione obbligatoria' => __('Formazione obbligatoria'),
            'addestramento' => __('Addestramento'),
            'corso normativo' => __('Corso normativo'),
        ];
    }

    /**
     * Get the available participant presence verification modes.
     *
     * @return array<int, string>
     */
    public static function availableParticipantPresenceVerifications(): array
    {
        return self::PARTICIPANT_PRESENCE_VERIFICATIONS;
    }

    /**
     * Get the translated labels for the participant presence verification modes.
     *
     * @return array<string, string>
     */
    public static function availableParticipantPresenceVerificationLabels(): array
    {
        return [
            'signature' => __('Firma presenza'),
            'badge_qr' => __('Badge/QR'),
            'other' => __('Altra modalità'),
        ];
    }

    /**
     * Get the available teaching methods for course programs.
     *
     * @return array<int, string>
     */
    public static function availableProgramTeachingMethods(): array
    {
        return array_keys(self::availableProgramTeachingMethodLabels());
    }

    /**
     * Get the translated labels for course program teaching methods.
     *
     * @return array<string, string>
     */
    public static function availableProgramTeachingMethodLabels(): array
    {
        return [
            'lezione_frontale_video_lezione' => __('Lezione frontale / video lezione'),
            'asincrona' => __('Asincrona'),
            'video_lezione_sincrona' => __('Video lezione Sincrona'),
            'discussione_casi' => __('Discussione casi'),
            'esercitazione' => __('Esercitazione'),
            'simulazione' => __('Simulazione'),
            'prova_pratica' => __('Prova pratica'),
            'role_playing' => __('Role-playing'),
            'lavoro_di_gruppo' => __('Lavoro di gruppo'),
            'minibreak_formativi' => __('Minibreak formativi'),
        ];
    }

    /**
     * Get the modules that belong to the course.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'belongsTo')->orderBy('order');
    }

    /**
     * Get the course enrollments.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CourseDocument::class)->latest();
    }

    public function teacherEnrollments(): HasMany
    {
        return $this->hasMany(CourseTeacherEnrollment::class);
    }

    public function tutorEnrollments(): HasMany
    {
        return $this->hasMany(CourseTutorEnrollment::class);
    }

    public function facultyMembers(): HasMany
    {
        return $this->hasMany(CourseFacultyMember::class);
    }

    public function originalCourse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_course_id');
    }

    public function fundingEntity(): BelongsTo
    {
        return $this->belongsTo(FundingEntity::class);
    }

    public function jobUnit(): BelongsTo
    {
        return $this->belongsTo(JobUnit::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function requiredLanguageLevel(): BelongsTo
    {
        return $this->belongsTo(LanguageLevel::class, 'required_language_level_id');
    }

    public function grantsLanguageLevel(): BelongsTo
    {
        return $this->belongsTo(LanguageLevel::class, 'grants_language_level_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CourseCategory::class, 'course_category_course')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class)
            ->orderBy('ragione_sociale')
            ->withTimestamps();
    }

    public function trainingPaths(): BelongsToMany
    {
        return $this->belongsToMany(TrainingPath::class, 'training_path_course')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'course_job_role')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function jobTasks(): BelongsToMany
    {
        return $this->belongsToMany(JobTask::class, 'course_job_task')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function jobUnits(): BelongsToMany
    {
        return $this->belongsToMany(JobUnit::class, 'course_job_unit')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('visible_to_all', true)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query
                        ->where('visible_to_all', false)
                        ->where(function (Builder $query): void {
                            $query
                                ->has('jobRoles')
                                ->orHas('jobTasks')
                                ->orHas('jobUnits');
                        })
                        ->where(function (Builder $query) use ($user): void {
                            $query
                                ->doesntHave('jobRoles')
                                ->orWhereHas('jobRoles', fn (Builder $query): Builder => $query->whereKey($user->job_role_id));
                        })
                        ->where(function (Builder $query) use ($user): void {
                            $query
                                ->doesntHave('jobTasks')
                                ->orWhereHas('jobTasks', fn (Builder $query): Builder => $query->whereKey($user->job_task_id));
                        })
                        ->where(function (Builder $query) use ($user): void {
                            $query
                                ->doesntHave('jobUnits')
                                ->orWhereHas('jobUnits', fn (Builder $query): Builder => $query->whereKey($user->job_unit_id));
                        });
                });
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($this->visible_to_all) {
            return true;
        }

        $hasRecipients = $this->jobRoles()->exists()
            || $this->jobTasks()->exists()
            || $this->jobUnits()->exists();

        if (! $hasRecipients) {
            // ponytail: empty restricted course hides from everyone; add explicit "none selected" UX if admins need it.
            return false;
        }

        return (! $this->jobRoles()->exists() || $this->jobRoles()->whereKey($user->job_role_id)->exists())
            && (! $this->jobTasks()->exists() || $this->jobTasks()->whereKey($user->job_task_id)->exists())
            && (! $this->jobUnits()->exists() || $this->jobUnits()->whereKey($user->job_unit_id)->exists());
    }

    public function enrollmentVisibilityMessageFor(User $user): ?string
    {
        if ($this->isVisibleTo($user)) {
            return null;
        }

        return __('L\'utente non rientra tra i destinatari del corso ":title", quindi l\'iscrizione non è stata creata.', [
            'title' => $this->title,
        ]);
    }

    public function familyRootCourseId(): int
    {
        return $this->original_course_id ?? (int) $this->getKey();
    }

    public function supportsClasses(): bool
    {
        return in_array($this->type, ['res', 'async'], true);
    }

    public function scopeExportableForAuditTrail(Builder $query): Builder
    {
        return $query->whereIn('type', self::AUDIT_TRAIL_TYPES);
    }

    public function scheduledModulesControlledByClasses(): HasMany
    {
        return $this->modules()->whereIn('type', [
            Module::TYPE_LIVE,
            Module::TYPE_RESIDENTIAL,
        ]);
    }

    /**
     * Get the SCORM packages for the course.
     */
    public function scormPackages(): HasMany
    {
        return $this->hasMany(ScormPackage::class);
    }

    public function riskBasedRequirements(): BelongsToMany
    {
        return $this->belongsToMany(RiskBasedRequirement::class)
            ->using(CourseRiskBasedRequirement::class)
            ->withPivot(['course_validity_types', 'integrative_start_risk_levels'])
            ->withTimestamps();
    }

    /**
     * @return Collection<int, CourseRiskRequirementValidityType>
     */
    public function courseValidityTypesForRequirement(RiskBasedRequirement $riskBasedRequirement): Collection
    {
        $types = $this->riskBasedRequirements
            ->firstWhere('id', $riskBasedRequirement->getKey())
            ?->pivot
            ?->course_validity_types;

        $normalizedTypes = is_string($types)
            ? json_decode($types, true)
            : $types;

        return collect(CourseRiskRequirementValidityType::normalizeMany(
            is_array($normalizedTypes) ? $normalizedTypes : []
        ));
    }

    public function courseHasValidityTypeForRequirement(
        RiskBasedRequirement $riskBasedRequirement,
        CourseRiskRequirementValidityType $validityType,
    ): bool {
        return $this->courseValidityTypesForRequirement($riskBasedRequirement)
            ->contains(fn (CourseRiskRequirementValidityType $type): bool => $type === $validityType);
    }

    /**
     * @return Collection<int, RiskLevel>
     */
    public function integrativeStartRiskLevelsForRequirement(RiskBasedRequirement $riskBasedRequirement): Collection
    {
        $encodedLevels = $this->riskBasedRequirements
            ->firstWhere('id', $riskBasedRequirement->getKey())
            ?->pivot
            ?->integrative_start_risk_levels;

        $levels = is_string($encodedLevels)
            ? json_decode($encodedLevels, true)
            : $encodedLevels;

        return collect(is_array($levels) ? $levels : [])
            ->map(fn (mixed $value): ?RiskLevel => RiskLevel::tryFrom((string) $value))
            ->filter()
            ->values();
    }

    /**
     * Get the users enrolled in the course.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')
            ->withPivot([
                'id',
                'current_module_id',
                'status',
                'assigned_at',
                'started_at',
                'completed_at',
                'expires_at',
                'last_accessed_at',
                'completion_percentage',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function getTeachersQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('course_teacher_enrollments', 'course_teacher_enrollments.user_id', '=', 'users.id')
            ->where('course_teacher_enrollments.course_id', $this->getKey())
            ->whereNull('course_teacher_enrollments.deleted_at')
            ->distinct()
            ->orderBy('users.surname')
            ->orderBy('users.name');
    }

    public function getTeachers(): EloquentCollection
    {
        return $this->getTeachersQuery()->get();
    }

    public function getTutorsQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('course_tutor_enrollments', 'course_tutor_enrollments.user_id', '=', 'users.id')
            ->where('course_tutor_enrollments.course_id', $this->getKey())
            ->whereNull('course_tutor_enrollments.deleted_at')
            ->distinct()
            ->orderBy('users.surname')
            ->orderBy('users.name');
    }

    public function getTutors(): EloquentCollection
    {
        return $this->getTutorsQuery()->get();
    }

    public function hasSatisfactionSurveyEnabled(): bool
    {
        return (bool) $this->has_satisfaction_survey;
    }

    public function requiresSatisfactionSurveyForCertificate(): bool
    {
        return $this->hasSatisfactionSurveyEnabled()
            && (bool) $this->satisfaction_survey_required_for_certificate;
    }

    public function satisfactionModules(): HasMany
    {
        return $this->modules()->where('type', Module::TYPE_SATISFACTION_QUIZ);
    }

    public function satisfactionModule(): ?Module
    {
        return $this->satisfactionModules()->orderBy('order')->first();
    }

    public function shouldCountModuleForCompletion(Module $module): bool
    {
        if ($module->type !== Module::TYPE_SATISFACTION_QUIZ) {
            return true;
        }

        return $this->requiresSatisfactionSurveyForCertificate();
    }

    /**
     * @return Collection<int, Module>
     */
    public function completionRelevantModules(): Collection
    {
        $this->loadMissing('modules');

        return $this->modules->values()->filter(
            fn (Module $module): bool => $this->shouldCountModuleForCompletion($module)
        )->values();
    }

    public function isLanguageVerificationCourse(): bool
    {
        return (bool) $this->is_language_verification_course;
    }
}
