<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleQuizSubmissionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_quiz_submission_id',
        'module_quiz_question_id',
        'module_quiz_answer_id',
        'question_number',
        'selected_option_key',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'question_number' => 'integer',
            'confidence' => 'decimal:4',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ModuleQuizSubmission::class, 'module_quiz_submission_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ModuleQuizQuestion::class, 'module_quiz_question_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(ModuleQuizAnswer::class, 'module_quiz_answer_id');
    }
}
