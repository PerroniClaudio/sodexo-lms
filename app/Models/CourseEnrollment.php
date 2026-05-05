<?php

namespace App\Models;

use Database\Factories\CourseEnrollmentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseEnrollment extends Model
{
    /** @use HasFactory<CourseEnrollmentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'course_user';

    protected $fillable = [
        'user_id',
        'course_id',
        'current_module_id',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'expires_at',
        'last_accessed_at',
        'completion_percentage',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'completion_percentage' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CourseEnrollment $enrollment): void {
            if ($enrollment->deleted_at !== null) {
                return;
            }

            $alreadyAssigned = static::query()
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $enrollment->course_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyAssigned) {
                throw new DomainException('The user already has an active enrollment for this course.');
            }
        });

        static::saving(function (CourseEnrollment $enrollment): void {
            if ($enrollment->current_module_id === null || $enrollment->course_id === null) {
                return;
            }

            $belongsToCourse = Module::query()
                ->whereKey($enrollment->current_module_id)
                ->where('belongsTo', (string) $enrollment->course_id)
                ->exists();

            if (! $belongsToCourse) {
                throw new DomainException('The current module must belong to the enrolled course.');
            }
        });
    }

    public static function enroll(User $user, Course $course): self
    {
        return DB::transaction(function () use ($user, $course): self {
            $existingEnrollment = static::withTrashed()
                ->where('user_id', $user->getKey())
                ->where('course_id', $course->getKey())
                ->first();

            if ($existingEnrollment !== null && ! $existingEnrollment->trashed()) {
                throw new DomainException('The user already has an active enrollment for this course.');
            }

            if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
                throw new DomainException('A deleted enrollment already exists for this user and course. Restore it instead of creating a new one.');
            }

            $course->loadMissing('modules');

            /** @var Collection<int, Module> $modules */
            $modules = $course->modules->values();
            $firstModule = $modules->first();
            $assignedAt = now();

            $enrollment = static::query()->create([
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
                'current_module_id' => $firstModule?->getKey(),
                'status' => $firstModule === null ? self::STATUS_COMPLETED : self::STATUS_ASSIGNED,
                'assigned_at' => $assignedAt,
                'completed_at' => $firstModule === null ? $assignedAt : null,
                'completion_percentage' => $firstModule === null ? 100 : 0,
            ]);

            $modules->each(function (Module $module, int $index) use ($enrollment): void {
                $enrollment->moduleProgresses()->create([
                    'module_id' => $module->getKey(),
                    'status' => $index === 0
                        ? ModuleProgress::STATUS_AVAILABLE
                        : ModuleProgress::STATUS_LOCKED,
                ]);
            });

            return $enrollment->fresh(['currentModule', 'moduleProgresses']);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function currentModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'current_module_id');
    }

    public function moduleProgresses(): HasMany
    {
        return $this->hasMany(ModuleProgress::class, 'course_user_id');
    }

    public function markAsInProgress(): void
    {
        $attributes = [
            'started_at' => $this->started_at ?? now(),
            'last_accessed_at' => now(),
            'status' => self::STATUS_IN_PROGRESS,
        ];

        $this->forceFill($attributes)->save();
    }

    public function syncProgressState(): void
    {
        $moduleProgresses = $this->moduleProgresses()->get();
        $totalModules = $moduleProgresses->count();

        if ($totalModules === 0) {
            $this->forceFill([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => $this->completed_at ?? now(),
                'completion_percentage' => 100,
            ])->save();

            return;
        }

        $completedModules = $moduleProgresses->where('status', ModuleProgress::STATUS_COMPLETED)->count();
        $hasStartedModules = $moduleProgresses->contains(
            fn (ModuleProgress $progress): bool => $progress->started_at !== null
                || in_array($progress->status, [
                    ModuleProgress::STATUS_IN_PROGRESS,
                    ModuleProgress::STATUS_FAILED,
                    ModuleProgress::STATUS_COMPLETED,
                ], true)
        );

        $isCompleted = $completedModules === $totalModules;

        $this->forceFill([
            'status' => $isCompleted
                ? self::STATUS_COMPLETED
                : ($hasStartedModules ? self::STATUS_IN_PROGRESS : self::STATUS_ASSIGNED),
            'completed_at' => $isCompleted ? ($this->completed_at ?? now()) : null,
            'completion_percentage' => (int) round(($completedModules / $totalModules) * 100),
            'last_accessed_at' => $hasStartedModules ? now() : $this->last_accessed_at,
        ])->save();
    }

    public function advanceAfterModuleCompletion(ModuleProgress $completedProgress): void
    {
        $this->loadMissing('course');

        /** @var Module $completedModule */
        $completedModule = $completedProgress->module()->firstOrFail();

        $nextModule = $this->course
            ->modules()
            ->where('order', '>', $completedModule->order)
            ->orderBy('order')
            ->first();

        if ($nextModule !== null) {
            $this->moduleProgresses()
                ->where('module_id', $nextModule->getKey())
                ->where('status', ModuleProgress::STATUS_LOCKED)
                ->update(['status' => ModuleProgress::STATUS_AVAILABLE]);

            $this->current_module_id = $nextModule->getKey();
        } else {
            $this->current_module_id = $completedModule->getKey();
        }

        $this->save();
        $this->syncProgressState();
    }
}
