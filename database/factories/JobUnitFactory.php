<?php

namespace Database\Factories;

use App\Models\JobUnit;
use App\Models\WorldCity;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

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
            ->first();

        if ($city === null) {
            throw new RuntimeException('No valid world city records found to build a consistent JobUnit. Seed geographic data before using JobUnitFactory.');
        }

        return [
            'name' => 'Sede '.$city->name,
            'description' => fake()->optional()->sentence(),
            'country_id' => $city->country_id,
            'region_id' => $city->division_id,
            'province_id' => $city->province_id,
            'city_id' => $city->id,
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
        ];
    }
}
