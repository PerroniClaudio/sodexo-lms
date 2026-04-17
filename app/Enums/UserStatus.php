<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'pending';           // Creato dall'admin, deve ancora attivarsi
    case ONBOARDING = 'onboarding';     // Mail validata, deve completare profilo
    case ACTIVE = 'active';             // Utente operativo
    case UPDATE_REQUIRED = 'update_required';  // Aggiornamento dati richiesto
    case SUSPENDED = 'suspended';       // Account bloccato temporaneamente

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'In Attesa di Attivazione',
            self::ONBOARDING => 'Completamento Profilo',
            self::ACTIVE => 'Attivo',
            self::UPDATE_REQUIRED => 'Aggiornamento Richiesto',
            self::SUSPENDED => 'Sospeso',
        };
    }

    /**
     * Get badge color class for the status
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'badge-warning',
            self::ONBOARDING => 'badge-info',
            self::ACTIVE => 'badge-success',
            self::UPDATE_REQUIRED => 'badge-warning',
            self::SUSPENDED => 'badge-error',
        };
    }

    /**
     * Check if user can access the platform
     */
    public function canAccessPlatform(): bool
    {
        return in_array($this, [self::ACTIVE, self::UPDATE_REQUIRED]);
    }

    /**
     * Check if user needs onboarding
     */
    public function needsOnboarding(): bool
    {
        return in_array($this, [self::PENDING, self::ONBOARDING]);
    }

    /**
     * Check if user is blocked
     */
    public function isBlocked(): bool
    {
        return $this === self::SUSPENDED;
    }
}
