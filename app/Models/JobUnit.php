<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description', 'country_id', 'region_id', 'province_id', 'city_id', 'address', 'postal_code'])]
class JobUnit extends Model
{
    use HasFactory, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'country_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(WorldDivision::class, 'region_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(WorldCity::class, 'city_id');
    }

    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([
                $this->address,
                $this->postal_code ? "{$this->postal_code} {$this->city?->name}" : $this->city?->name,
                $this->province?->name,
                $this->region?->name,
                $this->country?->name,
            ])->filter()->implode(', ')
        );
    }
}
