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
        'user_id',
        'course_id',
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
                ->where('user_id', $enrollment->user_id)
                ->where('course_id', $enrollment->course_id)
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
            'user_id' => $user->getKey(),
            'course_id' => $course->getKey(),
            'assigned_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
