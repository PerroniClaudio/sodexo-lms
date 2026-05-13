<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatisfactionSurveySubmissionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'satisfaction_survey_submission_id',
        'satisfaction_survey_question_id',
        'satisfaction_survey_answer_id',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(SatisfactionSurveySubmission::class, 'satisfaction_survey_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SatisfactionSurveyQuestion::class, 'satisfaction_survey_question_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(SatisfactionSurveyAnswer::class, 'satisfaction_survey_answer_id');
    }
}
