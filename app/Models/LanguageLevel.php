<?php

namespace App\Models;

use Database\Factories\LanguageLevelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LanguageLevel extends Model
{
    /** @use HasFactory<LanguageLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LanguageLevel $languageLevel): void {
            if (! $languageLevel->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($languageLevel->getKey())
                ->update(['is_default' => false]);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public static function defaultOrFirst(): ?self
    {
        return static::query()
            ->orderByDesc('is_default')
            ->ordered()
            ->first();
    }

    public function requiredByCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'required_language_level_id');
    }

    public function grantedByCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'grants_language_level_id');
    }
}
