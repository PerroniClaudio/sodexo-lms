<?php

namespace App\Models;

use Database\Factories\CourseClassScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseClassSchedule extends Model
{
    /** @use HasFactory<CourseClassScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'course_class_id',
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

    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }
}
