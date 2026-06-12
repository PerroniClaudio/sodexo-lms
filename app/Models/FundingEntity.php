<?php

namespace App\Models;

use Database\Factories\FundingEntityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundingEntity extends Model
{
    /** @use HasFactory<FundingEntityFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_name',
        'vat_number',
        'fiscal_code',
        'pec',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
