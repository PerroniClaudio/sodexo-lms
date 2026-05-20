<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'mux_asset_id',
        'mux_playback_id',
        'mux_upload_id',
        'mux_video_status',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
        ];
    }

    /**
     * Moduli che utilizzano questo video
     */
    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
