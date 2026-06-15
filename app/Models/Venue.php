<?php

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'country_id', 'region_id', 'province_id', 'city_id', 'postal_code', 'address'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory, SoftDeletes;

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
}
