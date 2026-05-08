<?php

namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class VideoValidator implements ModuleValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Validate the video module.
     */
    public function validate(Module $module): bool
    {
        $this->errors = [];

        if (empty($module->title)) {
            $this->errors[] = 'Il modulo video deve avere un titolo.';
        }

        if (! $module->video_id || ! $module->video) {
            $this->errors[] = 'Il modulo video deve avere un video associato.';

            return empty($this->errors);
        }

        if ($module->video->mux_video_status !== 'ready') {
            $this->errors[] = 'Il video deve essere in stato "ready" per essere valido.';
        }

        return empty($this->errors);
    }

    /**
     * Get validation error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
