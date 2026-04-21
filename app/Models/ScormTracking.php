<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScormTracking extends Model
{
    protected $table = 'scorm_tracking';

    protected $fillable = [
        'user_id',
        'scorm_package_id',
        'sco_identifier',
        'element',
        'value',
        'tracked_at',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'tracked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'scorm_package_id');
    }
}
