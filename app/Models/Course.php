<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Course extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'year',
        'expiry_date',
        'status',
        'has_satisfaction_survey',
        'satisfaction_survey_required_for_certificate',
        'hasMany',
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
            'has_satisfaction_survey' => 'boolean',
            'satisfaction_survey_required_for_certificate' => 'boolean',
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

    /**
     * Get the SCORM packages for the course.
     */
    public function scormPackages(): HasMany
    {
        return $this->hasMany(ScormPackage::class);
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
            ->selectRaw('COUNT(DISTINCT module_teacher_enrollments.module_id) as module_enrollments_count')
            ->join('module_teacher_enrollments', 'module_teacher_enrollments.user_id', '=', 'users.id')
            ->join('modules', 'modules.id', '=', 'module_teacher_enrollments.module_id')
            ->where('modules.belongsTo', (string) $this->getKey())
            ->whereNull('modules.deleted_at')
            ->whereNull('module_teacher_enrollments.deleted_at')
            ->groupBy('users.id')
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
            ->selectRaw('COUNT(DISTINCT module_tutor_enrollments.module_id) as module_enrollments_count')
            ->join('module_tutor_enrollments', 'module_tutor_enrollments.user_id', '=', 'users.id')
            ->join('modules', 'modules.id', '=', 'module_tutor_enrollments.module_id')
            ->where('modules.belongsTo', (string) $this->getKey())
            ->whereNull('modules.deleted_at')
            ->whereNull('module_tutor_enrollments.deleted_at')
            ->groupBy('users.id')
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
}
