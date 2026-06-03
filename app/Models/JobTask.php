<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description'])]
class JobTask extends Model
{
    use HasFactory, SoftDeletes;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_task_user')
            ->withPivot(['id', 'starts_at', 'ends_at'])
            ->withTimestamps();
    }

    public function jobSectors(): BelongsToMany
    {
        return $this->belongsToMany(JobSector::class, 'job_task_job_sector')
            ->withPivot(['task_risk_level', 'sector_risk_override'])
            ->withTimestamps();
    }
}
