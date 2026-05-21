<?php

namespace App\Enums;

enum InclusionType: string
{
    case SECTION = 'section';
    case DIVISION = 'division';
    case GROUP = 'group';
    case NACE_CLASS = 'class';
    case CATEGORY = 'category';
    case FULL_CODE = 'full_code';

    /**
     * Get corresponding HierarchyLevel for this inclusion type
     */
    public function toHierarchyLevel(): ?HierarchyLevel
    {
        return match ($this) {
            self::SECTION => HierarchyLevel::SECTION,
            self::DIVISION => HierarchyLevel::DIVISION,
            self::GROUP => HierarchyLevel::GROUP,
            self::NACE_CLASS => HierarchyLevel::NACE_CLASS,
            self::CATEGORY => HierarchyLevel::CATEGORY,
            self::FULL_CODE => HierarchyLevel::SUBCATEGORY,
        };
    }

    /**
     * Get human-readable label for the inclusion type
     */
    public function label(): string
    {
        return match ($this) {
            self::SECTION => 'Sezione',
            self::DIVISION => 'Divisione',
            self::GROUP => 'Gruppo',
            self::NACE_CLASS => 'Classe',
            self::CATEGORY => 'Categoria',
            self::FULL_CODE => 'Codice Completo',
        };
    }

    /**
     * Get English label for the inclusion type
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::SECTION => 'Section',
            self::DIVISION => 'Division',
            self::GROUP => 'Group',
            self::NACE_CLASS => 'Class',
            self::CATEGORY => 'Category',
            self::FULL_CODE => 'Full Code',
        };
    }

    /**
     * Get all inclusion types ordered by hierarchy
     */
    public static function ordered(): array
    {
        return [
            self::SECTION,
            self::DIVISION,
            self::GROUP,
            self::class,
            self::CATEGORY,
            self::FULL_CODE,
        ];
    }
}
