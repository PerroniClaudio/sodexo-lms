<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScormSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'user_id',
        'course_user_id',
        'module_id',
        'scorm_package_id',
        'session_id',
        'sco_identifier',
        'status',
        'runtime_snapshot',
        'recorded_session_seconds',
        'last_error_code',
        'initialized_at',
        'last_activity_at',
        'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'runtime_snapshot' => 'array',
            'initialized_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'terminated_at' => 'datetime',
            'recorded_session_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_user_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'scorm_package_id');
    }
}
