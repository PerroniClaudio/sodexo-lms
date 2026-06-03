<?php

namespace App\Enums;

enum CourseRiskRequirementValidityType: string
{
    case FirstAchievement = 'first_achievement';
    case Refresh = 'refresh';
    case Integrative = 'integrative';
    case Both = 'both';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::FirstAchievement->value => __('Solo primo conseguimento'),
            self::Refresh->value => __('Solo aggiornamento'),
            self::Integrative->value => __('Solo integrativo'),
            self::Both->value => __('Primo conseguimento e aggiornamento'),
        ];
    }

    public function matchesRequirementNeed(self $requiredType): bool
    {
        if ($requiredType === self::Integrative) {
            return $this === self::Integrative;
        }

        return $this === self::Both || $this === $requiredType;
    }
}
