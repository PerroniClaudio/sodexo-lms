<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScormTrackingArchive extends Model
{
    protected $table = 'scorm_tracking_archive';

    protected $fillable = [
        'original_tracking_id',
        'user_id',
        'course_user_id',
        'module_id',
        'scorm_package_id',
        'reset_batch_uuid',
        'archived_by_user_id',
        'sco_identifier',
        'element',
        'value',
        'tracked_at',
        'session_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'tracked_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
