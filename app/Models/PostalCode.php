<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostalCode extends Model
{
    protected $table = 'postal_codes';

    protected $fillable = [
        'city_id',
        'postal_code',
    ];

    /**
     * Get the city that owns this postal code.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(WorldCity::class, 'city_id');
    }
}
