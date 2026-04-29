<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'mux_asset_id',
        'mux_playback_id',
        'mux_upload_id',
        'mux_video_status',
    ];

    /**
     * Moduli che utilizzano questo video
     */
    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
