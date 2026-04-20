<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorldCountry extends Model
{
    protected $table = 'world_countries';

    public $timestamps = false;

    protected $fillable = [
        'continent_id',
        'code',
        'name',
        'full_name',
        'capital',
        'citizenship',
        'currency',
        'currency_code',
        'currency_sub_unit',
        'currency_symbol',
        'region_code',
        'sub_region_code',
        'eea',
        'calling_code',
        'flag',
    ];

    /**
     * Get the provinces for this country.
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'country_id');
    }

    /**
     * Get the regions (divisions) for this country.
     */
    public function regions(): HasMany
    {
        return $this->hasMany(WorldDivision::class, 'country_id');
    }
}
