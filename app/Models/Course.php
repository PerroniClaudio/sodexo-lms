<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
     * Get the teacher enrollments for the course.
     */
    public function teacherEnrollments(): HasMany
    {
        return $this->hasMany(CourseTeacherEnrollment::class);
    }

    /**
     * Get the tutor enrollments for the course.
     */
    public function tutorEnrollments(): HasMany
    {
        return $this->hasMany(CourseTutorEnrollment::class);
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

    /**
     * Get the teachers assigned to the course.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_teacher_enrollments')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get the tutors assigned to the course.
     */
    public function tutors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_tutor_enrollments')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->withTimestamps();
    }
}
