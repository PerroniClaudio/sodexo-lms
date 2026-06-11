<?php

namespace Database\Factories;

use App\Models\JobUnit;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<JobUnit>
 */
class JobUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = WorldCity::query()
            ->whereNotNull('country_id')
            ->whereNotNull('division_id')
            ->whereNotNull('province_id')
            ->inRandomOrder()
            ->first() ?? $this->createFallbackCity();

        return [
            'name' => 'Sede '.$city->name,
            'unit_code' => fake()->optional(0.7)->bothify('??###'),
            'description' => fake()->optional()->sentence(),
            'country_id' => $city->country_id,
            'region_id' => $city->division_id,
            'province_id' => $city->province_id,
            'city_id' => $city->id,
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
        ];
    }

    private function createFallbackCity(): WorldCity
    {
        $continentId = DB::table('world_continents')->value('id')
            ?? DB::table('world_continents')->insertGetId([
                'name' => 'Europe',
                'code' => 'EU',
            ]);

        $country = WorldCountry::query()->create([
            'continent_id' => $continentId,
            'name' => 'Italy',
            'full_name' => 'Italian Republic',
            'capital' => 'Rome',
            'code' => 'it',
            'code_alpha3' => 'ITA',
            'emoji' => 'IT',
            'has_division' => true,
            'currency_code' => 'EUR',
            'currency_name' => 'Euro',
            'tld' => '.it',
            'callingcode' => '39',
        ]);

        $division = WorldDivision::query()->create([
            'country_id' => $country->getKey(),
            'name' => 'Lazio',
            'full_name' => 'Lazio',
            'code' => '62',
            'has_city' => true,
        ]);

        $province = Province::query()->create([
            'country_id' => $country->getKey(),
            'region_id' => $division->getKey(),
            'code' => 'RM',
            'name' => 'Roma',
        ]);

        return WorldCity::query()->create([
            'country_id' => $country->getKey(),
            'division_id' => $division->getKey(),
            'province_id' => $province->getKey(),
            'name' => 'Roma',
            'full_name' => 'Roma',
            'code' => 'H501',
        ]);
    }
}
