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

#[Fillable(['name', 'description', 'manual_risk_level'])]
class JobSector extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'manual_risk_level' => RiskLevel::class,
        ];
    }

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

    public function jobTasks(): BelongsToMany
    {
        return $this->belongsToMany(JobTask::class, 'job_task_job_sector')
            ->withPivot(['task_risk_level', 'sector_risk_override'])
            ->withTimestamps();
    }

    public function getJobTaskRisk(int $jobTaskId): ?RiskLevel
    {
        $pivot = $this->jobTasks()->wherePivot('job_task_id', $jobTaskId)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        return RiskLevel::tryFrom($pivot->task_risk_level);
    }

    public function shouldTaskOverrideSectorRisk(int $jobTaskId): bool
    {
        $pivot = $this->jobTasks()->wherePivot('job_task_id', $jobTaskId)->first()?->pivot;

        return (bool) ($pivot?->sector_risk_override ?? false);
    }

    /**
     * Get the native risk level of this sector (based on ATECO codes)
     */
    public function getRiskLevel(): RiskLevel
    {
        return app(RiskCalculationService::class)
            ->getSectorRiskLevel($this->id);
    }

    public function getEffectiveWorkerRisk(int $jobTaskId): RiskLevel
    {
        return app(RiskCalculationService::class)
            ->getEffectiveWorkerRisk($this->id, $jobTaskId);
    }
}
