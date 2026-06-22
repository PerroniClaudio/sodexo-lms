<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPathDocument extends Model
{
    public const FILE_TYPES = CourseDocument::FILE_TYPES;

    public const CATEGORIES = CourseDocument::CATEGORIES;

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
            'training_path_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fileTypeLabels(): array
    {
        return CourseDocument::fileTypeLabels();
    }

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return CourseDocument::categoryLabels();
    }

    public function trainingPath(): BelongsTo
    {
        return $this->belongsTo(TrainingPath::class);
    }
}
