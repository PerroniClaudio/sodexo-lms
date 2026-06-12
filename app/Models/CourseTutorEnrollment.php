<?php

namespace App\Models;

use Database\Factories\CourseTutorEnrollmentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseTutorEnrollment extends Model
{
    /** @use HasFactory<CourseTutorEnrollmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'user_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CourseTutorEnrollment $enrollment): void {
            if ($enrollment->deleted_at !== null) {
                return;
            }

            $alreadyAssigned = static::query()
                ->where('course_id', $enrollment->course_id)
                ->where('user_id', $enrollment->user_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyAssigned) {
                throw new DomainException('The tutor already has an active enrollment for this course.');
            }
        });
    }

    public static function enroll(User $user, Course $course): self
    {
        return static::query()->create([
            'course_id' => $course->getKey(),
            'user_id' => $user->getKey(),
            'assigned_at' => now(),
        ]);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
