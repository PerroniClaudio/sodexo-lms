<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleQuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'text',
        'points',
        'correct_answer_id',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ModuleQuizAnswer::class, 'question_id');
    }

    public function correctAnswer(): BelongsTo
    {
        return $this->belongsTo(ModuleQuizAnswer::class, 'correct_answer_id');
    }

    public function isValid(): bool
    {
        return $this->answers()->count() === 4 && $this->correctAnswer()->exists();
    }
}
