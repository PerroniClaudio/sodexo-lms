<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatisfactionSurveyAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'satisfaction_survey_question_id',
        'sort_order',
        'text',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SatisfactionSurveyQuestion::class, 'satisfaction_survey_question_id');
    }
}
