<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SatisfactionSurveyQuestion extends Model
{
    use HasFactory, SoftDeletes;

    public const INPUT_TYPE_RADIO = 'radio';

    public const INPUT_TYPE_TEXTAREA = 'textarea';

    protected $fillable = [
        'satisfaction_survey_template_id',
        'sort_order',
        'text',
        'input_type',
        'excluded_course_types',
    ];

    protected function casts(): array
    {
        return [
            'excluded_course_types' => 'array',
        ];
    }

    public static function inputTypeOptions(): array
    {
        return [
            self::INPUT_TYPE_RADIO,
            self::INPUT_TYPE_TEXTAREA,
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SatisfactionSurveyTemplate::class, 'satisfaction_survey_template_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SatisfactionSurveyAnswer::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function usesTextarea(): bool
    {
        return $this->input_type === self::INPUT_TYPE_TEXTAREA;
    }

    public function usesRadio(): bool
    {
        return $this->input_type === self::INPUT_TYPE_RADIO;
    }

    public function isExcludedForCourseType(?string $courseType): bool
    {
        if ($courseType === null) {
            return false;
        }

        return in_array($courseType, $this->excluded_course_types ?? [], true);
    }
}
