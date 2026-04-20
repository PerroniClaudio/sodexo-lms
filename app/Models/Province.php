<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = [
        'country_id',
        'region_id',
        'code',
        'name',
    ];

    /**
     * Get the country that owns the province.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'country_id');
    }

    /**
     * Get the region that owns the province.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(WorldDivision::class, 'region_id');
    }

    /**
     * Get the cities in this province.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(WorldCity::class, 'province_id');
    }

    /**
     * Get the job units in this province.
     */
    public function jobUnits(): HasMany
    {
        return $this->hasMany(JobUnit::class, 'province_id');
    }
}
