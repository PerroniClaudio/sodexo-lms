<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleQuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'text',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ModuleQuizQuestion::class, 'question_id');
    }
}
