<?php

namespace App\Enums;

enum RiskLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    /**
     * Get numeric order value for comparison
     * Higher number = higher risk
     */
    public function order(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
        };
    }

    /**
     * Get human-readable label for the risk level
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Rischio Basso',
            self::MEDIUM => 'Rischio Medio',
            self::HIGH => 'Rischio Alto',
        };
    }

    /**
     * Get badge color class for the risk level
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::LOW => 'badge-success',
            self::MEDIUM => 'badge-warning',
            self::HIGH => 'badge-error',
        };
    }

    /**
     * Check if this risk level is higher than another
     */
    public function isHigherThan(self $other): bool
    {
        return $this->order() > $other->order();
    }

    /**
     * Check if this risk level is lower than another
     */
    public function isLowerThan(self $other): bool
    {
        return $this->order() < $other->order();
    }

    /**
     * Check if this risk level is equal to or higher than another
     */
    public function isAtLeast(self $other): bool
    {
        return $this->order() >= $other->order();
    }

    /**
     * Check if this risk level is equal to or lower than another
     */
    public function isAtMost(self $other): bool
    {
        return $this->order() <= $other->order();
    }

    /**
     * Get all risk levels ordered by severity (low to high)
     */
    public static function ordered(): array
    {
        return [
            self::LOW,
            self::MEDIUM,
            self::HIGH,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $riskLevel): string => $riskLevel->value,
            self::cases(),
        );
    }

    /**
     * Get the highest risk level between this and another
     */
    public function max(self $other): self
    {
        return $this->order() > $other->order() ? $this : $other;
    }

    /**
     * Get the lowest risk level between this and another
     */
    public function min(self $other): self
    {
        return $this->order() < $other->order() ? $this : $other;
    }
}
