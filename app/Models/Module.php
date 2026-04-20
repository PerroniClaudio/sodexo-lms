<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES_WITH_APPOINTMENT = [
        'res',
        'live',
    ];

    public const TYPES_WITHOUT_MANUAL_TITLE = [
        'learning_quiz',
        'satisfaction_quiz',
    ];

    public const TYPES = [
        'video',
        'res',
        'live',
        'learning_quiz',
        'satisfaction_quiz',
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
        'order',
        'is_live_teacher',
        'appointment_date',
        'appointment_start_time',
        'appointment_end_time',
        'status',
        'passing_score',
        'max_score',
        'belongsTo',
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
            'is_live_teacher' => 'boolean',
            'appointment_date' => 'datetime',
            'appointment_start_time' => 'datetime',
            'appointment_end_time' => 'datetime',
            'passing_score' => 'integer',
            'max_score' => 'integer',
        ];
    }

    /**
     * Get the course that owns the module.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'belongsTo');
    }

    /**
     * Get the progress records for the module.
     */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    /**
     * Get the available module types.
     *
     * @return array<int, string>
     */
    public static function availableTypes(): array
    {
        return self::TYPES;
    }

    /**
     * Get the translated labels for the available module types.
     *
     * @return array<string, string>
     */
    public static function availableTypeLabels(): array
    {
        return [
            'video' => __('Video'),
            'res' => __('Residential'),
            'live' => __('Live'),
            'learning_quiz' => __('Learning quiz'),
            'satisfaction_quiz' => __('Satisfaction quiz'),
        ];
    }

    /**
     * Get the available module statuses.
     *
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return self::STATUSES;
    }

    /**
     * Get the translated labels for the available module statuses.
     *
     * @return array<string, string>
     */
    public static function availableStatusLabels(): array
    {
        return [
            'draft' => __('Draft'),
            'published' => __('Published'),
            'archived' => __('Archived'),
        ];
    }

    /**
     * Determine if the given module type requires a manual title.
     */
    public static function requiresManualTitle(string $type): bool
    {
        return ! in_array($type, self::TYPES_WITHOUT_MANUAL_TITLE, true);
    }

    /**
     * Determine if the given module type requires appointment details.
     */
    public static function requiresAppointmentDetails(string $type): bool
    {
        return in_array($type, self::TYPES_WITH_APPOINTMENT, true);
    }

    /**
     * Get the default title for a given module type.
     */
    public static function defaultTitleForType(string $type): string
    {
        return self::availableTypeLabels()[$type] ?? $type;
    }

    /**
     * Determine if the module is a quiz.
     */
    public function isQuiz(): bool
    {
        return in_array($this->type, [
            'learning_quiz',
            'satisfaction_quiz',
        ], true);
    }

    /**
     * Determine if the module is a video.
     */
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }
}
