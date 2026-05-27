<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Services\RiskCalculationService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description'])]
class JobSector extends Model
{
    use HasFactory, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the NACE/ATECO codes associated with this job sector
     */
    public function naceAtecoCodes(): BelongsToMany
    {
        return $this->belongsToMany(NaceAteco::class, 'job_sector_nace_ateco', 'job_sector_id', 'nace_ateco_code')
            ->withPivot('inclusion_type')
            ->withTimestamps();
    }

    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'job_role_job_sector')
            ->withPivot('role_risk_level')
            ->withTimestamps();
    }

    public function getJobRoleRisk(int $jobRoleId): ?RiskLevel
    {
        $pivot = $this->jobRoles()->wherePivot('job_role_id', $jobRoleId)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        return RiskLevel::tryFrom($pivot->role_risk_level);
    }

    /**
     * Get the native risk level of this sector (based on ATECO codes)
     */
    public function getRiskLevel(): RiskLevel
    {
        return app(RiskCalculationService::class)
            ->getSectorRiskLevel($this->id);
    }

    public function getEffectiveWorkerRisk(int $jobRoleId): RiskLevel
    {
        return app(RiskCalculationService::class)
            ->getEffectiveWorkerRisk($this->id, $jobRoleId);
    }
}
