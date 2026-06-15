<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseDocument extends Model
{
    public const FILE_TYPES = [
        'document',
    ];

    public const CATEGORIES = [
        'registers',
        'verification_reports',
        'program',
        'final_tests',
        'intermediate_tests',
        'satisfaction',
        'appointment_letter',
    ];

    protected $fillable = [
        'file_name',
        'file_type',
        'category',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'course_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fileTypeLabels(): array
    {
        return [
            'document' => __('Documento'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            'registers' => __('Registri'),
            'verification_reports' => __('Verbali verifiche'),
            'program' => __('Programma'),
            'final_tests' => __('Prove finali'),
            'intermediate_tests' => __('Prove intermedie'),
            'satisfaction' => __('Gradimento'),
            'appointment_letter' => __('Lettera di incarico'),
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
