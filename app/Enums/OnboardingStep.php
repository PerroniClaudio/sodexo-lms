<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case PASSWORD_SETUP = 'password_setup';
    case PROFILE_COMPLETION = 'profile_completion';

    /**
     * Get human-readable label for the step
     */
    public function label(): string
    {
        return match ($this) {
            self::PASSWORD_SETUP => 'Impostazione Password',
            self::PROFILE_COMPLETION => 'Completamento Profilo',
        };
    }

    /**
     * Get the next step in the onboarding flow
     */
    public function next(): ?self
    {
        return match ($this) {
            self::PASSWORD_SETUP => self::PROFILE_COMPLETION,
            self::PROFILE_COMPLETION => null, // Last step
        };
    }

    /**
     * Get progress percentage
     */
    public function progressPercentage(): int
    {
        return match ($this) {
            self::PASSWORD_SETUP => 50,
            self::PROFILE_COMPLETION => 100,
        };
    }
}
