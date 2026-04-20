<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorldDivision extends Model
{
    protected $table = 'world_divisions';

    public $timestamps = false;

    protected $fillable = [
        'country_id',
        'name',
        'full_name',
        'code',
        'has_city',
    ];

    /**
     * Get the country that owns this division.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'country_id');
    }

    /**
     * Get the provinces in this region.
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'region_id');
    }

    /**
     * Get the cities directly in this division.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(WorldCity::class, 'division_id');
    }
}
