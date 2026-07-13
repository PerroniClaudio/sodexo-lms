<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    protected $fillable = [
        'occurred_at', 'actor_user_id', 'actor_label', 'company_division_id', 'origin',
        'action', 'subject_type', 'subject_id', 'subject_label', 'correlation_id', 'changes', 'metadata',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'changes' => 'array', 'metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function companyDivision(): BelongsTo
    {
        return $this->belongsTo(CompanyDivision::class);
    }
}
