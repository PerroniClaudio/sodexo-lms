<?php

namespace App;

enum OnboardingStep: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_SETUP = 'password_setup';
    case PROFILE_COMPLETION = 'profile_completion';

    /**
     * Get human-readable label for the step
     */
    public function label(): string
    {
        return match ($this) {
            self::EMAIL_VERIFICATION => 'Verifica Email',
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
            self::EMAIL_VERIFICATION => self::PASSWORD_SETUP,
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
            self::EMAIL_VERIFICATION => 33,
            self::PASSWORD_SETUP => 66,
            self::PROFILE_COMPLETION => 100,
        };
    }
}
