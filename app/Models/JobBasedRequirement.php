<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobBasedRequirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'rules',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_based_requirement_user')
            ->withPivot(['is_active', 'valid_from', 'calculated_at'])
            ->withTimestamps();
    }

    public function userCertificates(): BelongsToMany
    {
        return $this->belongsToMany(
            UserCertificate::class,
            'job_based_requirement_user_certificate',
            'job_based_requirement_id',
            'user_certificate_id',
        );
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->withTimestamps();
    }
}
