<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatisfactionSurveyQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'satisfaction_survey_template_id',
        'sort_order',
        'text',
    ];

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
}
