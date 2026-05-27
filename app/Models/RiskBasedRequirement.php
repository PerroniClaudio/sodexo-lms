<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiskBasedRequirement extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_limited_validity',
        'risk_levels',
        'validity_months',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk_levels' => AsEnumCollection::of(RiskLevel::class),
            'is_limited_validity' => 'boolean',
            'validity_months' => 'integer',
        ];
    }

    /**
     * Check if this requirement applies to a given risk level
     */
    public function appliesTo(RiskLevel $riskLevel): bool
    {
        return $this->risk_levels->contains($riskLevel);
    }

    /**
     * Check if this requirement has unlimited validity
     */
    public function hasUnlimitedValidity(): bool
    {
        return ! $this->is_limited_validity;
    }

    /**
     * Check if this requirement has limited validity
     */
    public function hasLimitedValidity(): bool
    {
        return $this->is_limited_validity;
    }

    /**
     * Get the validity period in a human-readable format
     */
    public function getValidityDescription(): string
    {
        if ($this->hasUnlimitedValidity()) {
            return 'Validità illimitata';
        }

        $months = $this->validity_months;

        if ($months % 12 === 0) {
            $years = $months / 12;

            return $years === 1 ? '1 anno' : "{$years} anni";
        }

        return $months === 1 ? '1 mese' : "{$months} mesi";
    }

    /**
     * Scope to get requirements for a specific risk level
     */
    public function scopeForRiskLevel($query, RiskLevel $riskLevel)
    {
        return $query->whereJsonContains('risk_levels', $riskLevel->value);
    }

    public function userCertificates(): BelongsToMany
    {
        return $this->belongsToMany(
            UserCertificate::class,
            'requirement_user_certificate',
            'risk_based_requirement_id',
            'user_certificate_id'
        );
    }

    /**
     * Get the validity in years and months
     *
     * @return array{years: int, months: int}
     */
    public function getValidityParts(): array
    {
        if (! $this->is_limited_validity || $this->validity_months === null) {
            return ['years' => 0, 'months' => 0];
        }

        $years = intdiv($this->validity_months, 12);
        $months = $this->validity_months % 12;

        return ['years' => $years, 'months' => $months];
    }
}
