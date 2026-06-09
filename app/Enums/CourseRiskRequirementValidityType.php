<?php

namespace App\Enums;

enum CourseRiskRequirementValidityType: string
{
    case FirstAchievement = 'first_achievement';
    case Refresh = 'refresh';
    case Integrative = 'integrative';

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
            self::FirstAchievement->value => self::FirstAchievement->label(),
            self::Refresh->value => self::Refresh->label(),
            self::Integrative->value => self::Integrative->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::FirstAchievement => __('Primo conseguimento'),
            self::Refresh => __('Aggiornamento'),
            self::Integrative => __('Integrativo'),
        };
    }

    /**
     * @param  iterable<int, self|string>  $types
     * @return array<int, self>
     */
    public static function normalizeMany(iterable $types): array
    {
        $normalizedTypes = [];

        foreach ($types as $type) {
            $normalizedType = $type instanceof self
                ? $type
                : self::tryFrom((string) $type);

            if ($normalizedType instanceof self) {
                $normalizedTypes[$normalizedType->value] = $normalizedType;
            }
        }

        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => array_key_exists($type->value, $normalizedTypes),
        ));
    }

    /**
     * @param  iterable<int, self|string>  $types
     * @return array<int, string>
     */
    public static function labelsFor(iterable $types): array
    {
        return array_map(
            static fn (self $type): string => $type->label(),
            self::normalizeMany($types),
        );
    }

    /**
     * @param  iterable<int, self|string>  $types
     */
    public static function labelsText(iterable $types, string $separator = ', '): string
    {
        return implode($separator, self::labelsFor($types));
    }
}
