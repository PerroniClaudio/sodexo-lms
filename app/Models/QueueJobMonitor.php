<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJobMonitor extends Model
{
    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'job_name',
        'status',
        'attempts',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
