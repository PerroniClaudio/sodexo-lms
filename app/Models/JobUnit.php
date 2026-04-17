<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'country', 'region', 'province', 'city', 'address', 'postal_code', 'is_active'])]
class JobUnit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address,
            $this->postal_code ? "{$this->postal_code} {$this->city}" : $this->city,
            $this->province,
            $this->region,
            $this->country,
        ])->filter()->implode(', ');
    }
}
