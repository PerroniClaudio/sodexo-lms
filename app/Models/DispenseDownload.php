<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispenseDownload extends Model
{
    protected $fillable = [
        'course_enrollment_id',
        'module_teaching_material_id',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'course_enrollment_id' => 'integer',
            'module_teaching_material_id' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }

    public function courseEnrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ModuleTeachingMaterial::class, 'module_teaching_material_id');
    }
}
