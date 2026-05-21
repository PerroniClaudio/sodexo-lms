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

    /**
     * Get the job titles associated with this job sector
     */
    public function jobTitles(): BelongsToMany
    {
        return $this->belongsToMany(JobTitle::class, 'job_sector_job_title')
            ->withPivot('title_risk_level')
            ->withTimestamps();
    }

    /**
     * Get the risk level for a specific job title in this sector
     */
    public function getJobTitleRisk(int $jobTitleId): ?RiskLevel
    {
        $pivot = $this->jobTitles()->wherePivot('job_title_id', $jobTitleId)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        return RiskLevel::tryFrom($pivot->title_risk_level);
    }

    /**
     * Get the native risk level of this sector (based on ATECO codes)
     */
    public function getRiskLevel(): RiskLevel
    {
        return app(RiskCalculationService::class)
            ->getSectorRiskLevel($this->id);
    }

    /**
     * Get the effective risk level for a worker in this sector with a specific job title
     */
    public function getEffectiveWorkerRisk(int $jobTitleId): RiskLevel
    {
        return app(RiskCalculationService::class)
            ->getEffectiveWorkerRisk($this->id, $jobTitleId);
    }
}
