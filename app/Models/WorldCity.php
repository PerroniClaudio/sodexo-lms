<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorldCity extends Model
{
    protected $table = 'world_cities';

    public $timestamps = false;

    protected $fillable = [
        'country_id',
        'division_id',
        'province_id',
        'name',
        'full_name',
        'code',
    ];

    /**
     * Get the country that owns this city.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'country_id');
    }

    /**
     * Get the division (region) that owns this city.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(WorldDivision::class, 'division_id');
    }

    /**
     * Get the province that owns this city.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    /**
     * Get all postal codes for this city.
     */
    public function postalCodes(): HasMany
    {
        return $this->hasMany(PostalCode::class, 'city_id');
    }
}
