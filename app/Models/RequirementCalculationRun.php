<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementCalculationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope',
        'status',
        'triggered_by_user_id',
        'target_user_id',
        'started_at',
        'finished_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
