<?php

namespace Database\Seeders;

use App\Models\LanguageLevel;
use Illuminate\Database\Seeder;

class LanguageLevelSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'a1', 'sort_order' => 1, 'is_default' => true],
            ['name' => 'a2', 'sort_order' => 2, 'is_default' => false],
            ['name' => 'b1', 'sort_order' => 3, 'is_default' => false],
            ['name' => 'b2', 'sort_order' => 4, 'is_default' => false],
            ['name' => 'c1', 'sort_order' => 5, 'is_default' => false],
            ['name' => 'c2', 'sort_order' => 6, 'is_default' => false],
        ])->each(function (array $attributes): void {
            LanguageLevel::query()->updateOrCreate(
                ['name' => $attributes['name']],
                $attributes,
            );
        });
    }
}
