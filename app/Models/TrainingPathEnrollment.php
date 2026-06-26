<?php

namespace App\Models;

use Database\Factories\TrainingPathEnrollmentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPathEnrollment extends Model
{
    /** @use HasFactory<TrainingPathEnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'training_path_user';

    protected $fillable = [
        'user_id',
        'training_path_id',
        'current_course_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'current_course_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TrainingPathEnrollment $enrollment): void {
            if ($enrollment->deleted_at !== null) {
                return;
            }

            $alreadyAssigned = static::query()
                ->where('user_id', $enrollment->user_id)
                ->where('training_path_id', $enrollment->training_path_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyAssigned) {
                throw new DomainException('The user already has an active enrollment for this training path.');
            }
        });
    }

    public static function enroll(User $user, TrainingPath $trainingPath): self
    {
        $visibilityErrors = $trainingPath->enrollmentVisibilityErrorsFor($user);

        if ($visibilityErrors !== []) {
            throw new DomainException(implode(' ', $visibilityErrors));
        }

        $existingEnrollment = static::withTrashed()
            ->where('user_id', $user->getKey())
            ->where('training_path_id', $trainingPath->getKey())
            ->first();

        if ($existingEnrollment !== null && ! $existingEnrollment->trashed()) {
            throw new DomainException('The user already has an active enrollment for this training path.');
        }

        if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
            throw new DomainException('A deleted enrollment already exists for this user and training path. Restore it instead of creating a new one.');
        }

        return static::query()->create([
            'user_id' => $user->getKey(),
            'training_path_id' => $trainingPath->getKey(),
            'current_course_id' => null,
            'assigned_at' => now(),
        ]);
    }

    public function trainingPath(): BelongsTo
    {
        return $this->belongsTo(TrainingPath::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'current_course_id');
    }
}
