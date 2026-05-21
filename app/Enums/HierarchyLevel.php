<?php

namespace App\Enums;

enum HierarchyLevel: int
{
    case SECTION = 1;
    case DIVISION = 2;
    case GROUP = 3;
    case NACE_CLASS = 4;
    case CATEGORY = 5;
    case SUBCATEGORY = 6;

    /**
     * Get human-readable label for the hierarchy level
     */
    public function label(): string
    {
        return match ($this) {
            self::SECTION => 'Sezione',
            self::DIVISION => 'Divisione',
            self::GROUP => 'Gruppo',
            self::NACE_CLASS => 'Classe',
            self::CATEGORY => 'Categoria',
            self::SUBCATEGORY => 'Sottocategoria',
        };
    }

    /**
     * Get English label for the hierarchy level
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::SECTION => 'Section',
            self::DIVISION => 'Division',
            self::GROUP => 'Group',
            self::NACE_CLASS => 'Class',
            self::CATEGORY => 'Category',
            self::SUBCATEGORY => 'Subcategory',
        };
    }

    /**
     * Check if this is a NACE level (hierarchy = 4)
     */
    public function isNace(): bool
    {
        return $this === self::NACE_CLASS;
    }

    /**
     * Check if this is an ATECO level (hierarchy = 6)
     */
    public function isAteco(): bool
    {
        return $this === self::SUBCATEGORY;
    }

    /**
     * Get all levels that can be linked to job sectors (NACE or ATECO)
     */
    public static function linkableLevels(): array
    {
        return [
            self::NACE_CLASS,
            self::SUBCATEGORY,
        ];
    }
}
