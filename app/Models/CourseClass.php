<?php

namespace App\Models;

use Database\Factories\CourseClassFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CourseClass extends Model
{
    /** @use HasFactory<CourseClassFactory> */
    use HasFactory, SoftDeletes;

    public const MAX_USERS = 30;

    protected $fillable = [
        'module_id',
        'name',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseClassSchedule::class)->orderBy('starts_at');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(CourseClassUser::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(CourseClassTeacher::class);
    }

    public function tutorAssignments(): HasMany
    {
        return $this->hasMany(CourseClassTutor::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_class_users')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_class_teachers')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function tutors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_class_tutors')
            ->withPivot([
                'id',
                'assigned_at',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function remainingUserSlots(): int
    {
        $assignedUsersCount = $this->relationLoaded('userAssignments')
            ? $this->userAssignments->count()
            : $this->userAssignments()->count();

        return max(0, self::MAX_USERS - $assignedUsersCount);
    }

    public function hasUserCapacity(int $additionalUsers = 1): bool
    {
        return $this->remainingUserSlots() >= $additionalUsers;
    }

    public function scheduledStartAt(): ?Carbon
    {
        return $this->resolvedSchedule()?->starts_at;
    }

    public function scheduledEndAt(): ?Carbon
    {
        return $this->resolvedSchedule()?->ends_at;
    }

    public function resolvedSchedule(?Carbon $reference = null): ?CourseClassSchedule
    {
        $reference ??= now();
        $schedules = $this->orderedSchedules();

        return $schedules
            ->first(fn (CourseClassSchedule $schedule): bool => $schedule->starts_at->lte($reference) && $schedule->ends_at->gt($reference))
            ?? $schedules->first(fn (CourseClassSchedule $schedule): bool => $schedule->starts_at->gt($reference))
            ?? $schedules->last();
    }

    /**
     * @return Collection<int, CourseClassSchedule>
     */
    public function orderedSchedules(): Collection
    {
        $schedules = $this->relationLoaded('schedules')
            ? $this->schedules
            : $this->schedules()->get();

        return $schedules->sortBy('starts_at')->values();
    }
}
