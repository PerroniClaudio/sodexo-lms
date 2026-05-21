<?php

namespace App\Models;

use App\Enums\HierarchyLevel;
use App\Enums\RiskLevel;
use App\Services\RiskCalculationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NaceAteco extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'code';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'nace_ateco';

    protected $fillable = [
        'section',
        'code',
        'order',
        'hierarchy',
        'title_it',
        'title_en',
        'risk',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'hierarchy' => HierarchyLevel::class,
            'risk' => RiskLevel::class,
        ];
    }

    /**
     * Get job sectors linked to this NACE/ATECO code
     */
    public function jobSectors(): BelongsToMany
    {
        return $this->belongsToMany(JobSector::class, 'job_sector_nace_ateco', 'nace_ateco_code', 'job_sector_id')
            ->withPivot('inclusion_type')
            ->withTimestamps();
    }

    /**
     * Check if this is a NACE level (hierarchy = 4)
     */
    public function isNace(): bool
    {
        return $this->hierarchy?->isNace() ?? false;
    }

    /**
     * Check if this is an ATECO level (hierarchy = 6)
     */
    public function isAteco(): bool
    {
        return $this->hierarchy?->isAteco() ?? false;
    }

    /**
     * Get the default title (Italian)
     */
    public function getDefaultTitleAttribute(): string
    {
        return $this->title_it;
    }

    /**
     * Scope to filter by hierarchy level
     */
    public function scopeByHierarchy($query, HierarchyLevel|int $level)
    {
        $value = $level instanceof HierarchyLevel ? $level->value : $level;

        return $query->where('hierarchy', $value);
    }

    /**
     * Scope to filter by risk level
     */
    public function scopeByRisk($query, RiskLevel|string $risk)
    {
        $value = $risk instanceof RiskLevel ? $risk->value : $risk;

        return $query->where('risk', $value);
    }

    /**
     * Scope to get only NACE codes (hierarchy = 4)
     */
    public function scopeNaceOnly($query)
    {
        return $query->where('hierarchy', HierarchyLevel::NACE_CLASS->value);
    }

    /**
     * Scope to get only ATECO codes (hierarchy = 6)
     */
    public function scopeAtecoOnly($query)
    {
        return $query->where('hierarchy', HierarchyLevel::SUBCATEGORY->value);
    }

    /**
     * Scope to get codes that can be linked to job sectors
     */
    public function scopeLinkable($query)
    {
        return $query->whereIn('hierarchy', [
            HierarchyLevel::NACE_CLASS->value,
            HierarchyLevel::SUBCATEGORY->value,
        ]);
    }

    /**
     * Find the job sector for this ATECO code using hierarchical lookup
     */
    public function findJobSector(): ?JobSector
    {
        return app(RiskCalculationService::class)
            ->findSectorByAtecoCode($this->code);
    }

    /**
     * Get the section (macro-category) for this ATECO code
     */
    public function getSectionRecord(): ?self
    {
        return app(RiskCalculationService::class)
            ->getSectionForCode($this->code);
    }
}
