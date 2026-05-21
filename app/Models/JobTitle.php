<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description'])]
class JobTitle extends Model
{
    use HasFactory, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the job sectors associated with this job title
     */
    public function jobSectors(): BelongsToMany
    {
        return $this->belongsToMany(JobSector::class, 'job_sector_job_title')
            ->withPivot('title_risk_level')
            ->withTimestamps();
    }
}
