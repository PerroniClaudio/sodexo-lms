<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditArchive extends Model
{
    protected $fillable = ['period_start', 'period_end', 'disk', 'path', 'checksum', 'event_count', 'archived_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'archived_at' => 'datetime'];
    }
}
