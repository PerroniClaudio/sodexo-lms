<?php

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ragione_sociale',
    ];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->orderBy('title')
            ->withTimestamps();
    }
}
