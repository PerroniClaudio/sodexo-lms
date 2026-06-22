<?php

namespace App\Models;

use Database\Factories\TrainingPathFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPath extends Model
{
    /** @use HasFactory<TrainingPathFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'code',
        'description',
        'status',
        'visible_to_all',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'visible_to_all' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return Course::availableStatuses();
    }

    /**
     * @return array<string, string>
     */
    public static function availableStatusLabels(): array
    {
        return Course::availableStatusLabels();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TrainingPathDocument::class)->latest();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'training_path_course')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('courses.title');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingPathEnrollment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'training_path_user')
            ->withPivot(['id', 'assigned_at', 'deleted_at'])
            ->withTimestamps();
    }

    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'training_path_job_role')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function jobTasks(): BelongsToMany
    {
        return $this->belongsToMany(JobTask::class, 'training_path_job_task')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function jobUnits(): BelongsToMany
    {
        return $this->belongsToMany(JobUnit::class, 'training_path_job_unit')
            ->orderBy('name')
            ->withTimestamps();
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('visible_to_all', true)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query
                        ->where('visible_to_all', false)
                        ->where(function (Builder $query): void {
                            $query
                                ->has('jobRoles')
                                ->orHas('jobTasks')
                                ->orHas('jobUnits');
                        })
                        ->where(function (Builder $query) use ($user): void {
                            $query
                                ->doesntHave('jobRoles')
                                ->orWhereHas('jobRoles', fn (Builder $query): Builder => $query->whereKey($user->job_role_id));
                        })
                        ->where(function (Builder $query) use ($user): void {
                            $query
                                ->doesntHave('jobTasks')
                                ->orWhereHas('jobTasks', fn (Builder $query): Builder => $query->whereKey($user->job_task_id));
                        })
                        ->where(function (Builder $query) use ($user): void {
                            $query
                                ->doesntHave('jobUnits')
                                ->orWhereHas('jobUnits', fn (Builder $query): Builder => $query->whereKey($user->job_unit_id));
                        });
                });
        });
    }
}
