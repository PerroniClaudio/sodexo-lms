<?php

namespace App\Models;

use App\Enums\OnboardingStep;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'email',
        'password',
        'account_state',
        'profile_completed_at',
        'last_data_update_request',
        'onboarding_step',
        'name',
        'surname',
        'fiscal_code',
        'birth_date',
        'birth_place',
        'gender',
        'phone_prefix',
        'phone',
        'home_country_id',
        'home_region_id',
        'home_province_id',
        'home_city_id',
        'address',
        'postal_code',
        'job_unit_id',
        'job_category_id',
        'job_level_id',
        'job_title_id',
        'job_role_id',
        'job_sector_id',
        'is_foreigner_or_immigrant',
        'notes',
    ];
    
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'datapsw' => 'datetime',
            'data_richiesta_mail' => 'datetime',
            'profile_completed_at' => 'datetime',
            'last_data_update_request' => 'datetime',
            'birth_date' => 'date',
            'is_foreigner_or_immigrant' => 'boolean',
            'account_state' => UserStatus::class,
            'onboarding_step' => OnboardingStep::class,
        ];
    }

    /**
     * Job Unit relazione
     */
    public function jobUnit(): BelongsTo
    {
        return $this->belongsTo(JobUnit::class);
    }

    /**
     * Job Category relazione
     */
    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class);
    }

    /**
     * Job Level relazione
     */
    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class);
    }

    /**
     * Job Title relazione
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * Job Role relazione
     */
    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    /**
     * Job Sector relazione
     */
    public function jobSector(): BelongsTo
    {
        return $this->belongsTo(JobSector::class);
    }

    /**
     * Get the course enrollments for the user.
     */
    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Get the courses assigned to the user.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_user')
            ->withPivot([
                'id',
                'current_module_id',
                'status',
                'assigned_at',
                'started_at',
                'completed_at',
                'expires_at',
                'last_accessed_at',
                'completion_percentage',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->surname}";
    }
    
    public function homeCountry(): BelongsTo
    {
        return $this->belongsTo(WorldCountry::class, 'home_country_id');
    }

    public function homeRegion(): BelongsTo
    {
        return $this->belongsTo(WorldDivision::class, 'home_region_id');
    }

    public function homeProvince(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'home_province_id');
    }

    public function homeCity(): BelongsTo
    {
        return $this->belongsTo(WorldCity::class, 'home_city_id');
    }

    /**
     * Get full home address attribute
     */
    public function getFullHomeAddressAttribute(): ?string
    {
        if (! $this->address) {
            return null;
        }

        return collect([
            $this->address,
            $this->postal_code ? "{$this->postal_code} {$this->homeCity?->name}" : $this->homeCity?->name,
            $this->homeProvince?->name,
            $this->homeRegion?->name,
            $this->homeCountry?->name,
        ])->filter()->implode(', ');
    }

    /**
     * Scope per filtrare utenti attivi
     */
    public function scopeActive($query)
    {
        return $query->where('account_state', UserStatus::ACTIVE->value);
    }

    /**
     * Scope per filtrare utenti stranieri o immigrati
     */
    public function scopeForeignerOrImmigrant($query)
    {
        return $query->where('is_foreigner_or_immigrant', true);
    }

    /**
     * Scope per filtrare utenti in onboarding
     */
    public function scopeNeedingOnboarding($query)
    {
        return $query->whereIn('account_state', [
            UserStatus::PENDING->value,
            UserStatus::ONBOARDING->value,
        ]);
    }

    /**
     * Check if user can access the platform
     */
    public function canAccessPlatform(): bool
    {
        return $this->account_state->canAccessPlatform();
    }

    /**
     * Check if user needs to complete onboarding
     */
    public function needsOnboarding(): bool
    {
        return $this->account_state->needsOnboarding();
    }

    /**
     * Check if user account is blocked
     */
    public function isBlocked(): bool
    {
        return $this->account_state->isBlocked();
    }

    /**
     * Check if user needs to update their data
     */
    public function needsDataUpdate(): bool
    {
        return $this->account_state === UserStatus::UPDATE_REQUIRED;
    }

    /**
     * Check if profile is completed
     */
    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }

    /**
     * Mark profile as completed
     */
    public function markProfileAsCompleted(): void
    {
        $this->update([
            'profile_completed_at' => now(),
            'account_state' => UserStatus::ACTIVE,
            'onboarding_step' => null,
        ]);
    }

    /**
     * Request data update
     */
    public function requestDataUpdate(): void
    {
        $this->update([
            'account_state' => UserStatus::UPDATE_REQUIRED,
            'last_data_update_request' => now(),
        ]);
    }

    /**
     * Mark data as updated
     */
    public function markDataAsUpdated(): void
    {
        $this->update([
            'account_state' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Suspend account
     */
    public function suspend(): void
    {
        $this->update(['account_state' => UserStatus::SUSPENDED]);
    }

    /**
     * Reactivate suspended account
     */
    public function reactivate(): void
    {
        $this->update(['account_state' => UserStatus::ACTIVE]);
    }

    /**
     * Move user to onboarding phase
     */
    public function moveToOnboarding(): void
    {
        $this->update([
            'account_state' => UserStatus::ONBOARDING,
            'onboarding_step' => OnboardingStep::PASSWORD_SETUP,
        ]);
    }

    /**
     * Advance to next onboarding step
     */
    public function advanceOnboardingStep(): void
    {
        if ($this->onboarding_step) {
            $nextStep = $this->onboarding_step->next();

            if ($nextStep) {
                $this->update(['onboarding_step' => $nextStep]);
            } else {
                // Last step completed
                $this->markProfileAsCompleted();
            }
        }
    }

    /**
     * Get current onboarding progress percentage
     */
    public function onboardingProgress(): int
    {
        return $this->onboarding_step?->progressPercentage() ?? 0;
    }

    /**
     * Accessor per job_country (ISO2) dalla jobUnit
     */
    public function getJobCountryAttribute(): ?string
    {
        return $this->jobUnit?->country?->code ?? null;
    }

    /**
     * Accessor per job_region (nome) dalla jobUnit
     */
    public function getJobRegionAttribute(): ?string
    {
        return $this->jobUnit?->region?->name ?? null;
    }

    /**
     * Accessor per job_province (sigla) dalla jobUnit
     */
    public function getJobProvinceAttribute(): ?string
    {
        return $this->jobUnit?->province?->code ?? null;
    }

}
