<?php

namespace App\Support;

class CloudStorage
{
    public static function disk(): string
    {
        return (string) config('filesystems.cloud', 's3');
    }
}
