<?php

namespace Database\Seeders;

use App\Models\LanguageLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SodexoLanguageLevelSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            LanguageLevel::query()->delete();

            collect([
                ['name' => 'Base', 'sort_order' => 1, 'is_default' => true],
                ['name' => 'Avanzato', 'sort_order' => 2, 'is_default' => false],
            ])->each(function (array $attributes): void {
                LanguageLevel::query()->create($attributes);
            });
        });
    }
}
