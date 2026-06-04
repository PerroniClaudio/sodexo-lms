<?php

namespace App\Models;

use Database\Factories\UserCertificateFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCertificateFile extends Model
{
    /** @use HasFactory<UserCertificateFileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_certificate_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(UserCertificate::class, 'user_certificate_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
