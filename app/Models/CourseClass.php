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

class CourseClass extends Model
{
    /** @use HasFactory<CourseClassFactory> */
    use HasFactory, SoftDeletes;

    public const MAX_USERS = 30;

    protected $fillable = [
        'course_id',
        'name',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(CourseClassUser::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(CourseClassTeacher::class);
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

    public function scheduledStartAt(): Carbon
    {
        return $this->starts_at;
    }

    public function scheduledEndAt(): Carbon
    {
        return $this->ends_at;
    }
}
