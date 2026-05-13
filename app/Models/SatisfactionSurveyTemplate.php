<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SatisfactionSurveyTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_active',
        'created_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SatisfactionSurveyQuestion::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SatisfactionSurveySubmission::class);
    }

    public static function active(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->with(['questions.answers'])
            ->latest('activated_at')
            ->latest('id')
            ->first();
    }

    public function isUsable(): bool
    {
        $this->loadMissing('questions.answers');

        return $this->questions->isNotEmpty()
            && $this->questions->every(
                fn (SatisfactionSurveyQuestion $question): bool => $question->answers->count() >= 2
            );
    }
}
