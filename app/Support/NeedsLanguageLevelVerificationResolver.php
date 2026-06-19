<?php

namespace App\Support;

class NeedsLanguageLevelVerificationResolver
{
    public function resolve(mixed $isForeignerOrImmigrant = false): bool
    {
        if ((bool) config('app.use_immigrant_functions', false)) {
            return filter_var($isForeignerOrImmigrant, FILTER_VALIDATE_BOOL);
        }

        return (bool) config('app.default_check_language_knowledge', false);
    }
}
