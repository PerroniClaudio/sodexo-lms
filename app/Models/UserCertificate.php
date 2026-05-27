<?php

namespace App\Models;

use Database\Factories\UserCertificateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserCertificate extends Model
{
    /** @use HasFactory<UserCertificateFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'internal_course_id',
        'name',
        'description',
        'file_path',
        'is_internal',
        'issued_at',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function internalCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'internal_course_id');
    }

    public function riskBasedRequirements(): BelongsToMany
    {
        return $this->belongsToMany(
            RiskBasedRequirement::class,
            'requirement_user_certificate',
            'user_certificate_id',
            'risk_based_requirement_id'
        );
    }

    public function scopeValidOn(Builder $query, ?string $date = null): Builder
    {
        $comparisonDate = $date ?? now()->toDateString();

        return $query->where(function (Builder $certificateQuery) use ($comparisonDate): void {
            $certificateQuery
                ->whereNull('expires_at')
                ->orWhereDate('expires_at', '>=', $comparisonDate);
        });
    }
}
