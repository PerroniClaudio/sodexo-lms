<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

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
        'appointment_date',
        'appointment_start_time',
        'appointment_end_time',
        'status',
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
            'appointment_date' => 'datetime',
            'appointment_start_time' => 'datetime',
            'appointment_end_time' => 'datetime',
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
            'res' => __('Residenziale'),
            'live' => __('Live'),
            'learning_quiz' => __('Quiz di apprendimento'),
            'satisfaction_quiz' => __('Quiz di gradimento'),
        ];
    }

    /**
     * Get the translated labels for the available module statuses.
     *
     * @return array<string, string>
     */
    public static function availableStatusLabels(): array
    {
        return [
            'draft' => __('Bozza'),
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
     * Get the default title for a given module type.
     */
    public static function defaultTitleForType(string $type): string
    {
        return self::availableTypeLabels()[$type] ?? $type;
    }
}
