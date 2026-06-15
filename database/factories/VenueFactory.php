<?php

namespace Database\Factories;

use App\Models\JobUnit;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jobUnit = JobUnit::factory()->create();

        return [
            'name' => 'Sede '.$jobUnit->city?->name,
            'country_id' => $jobUnit->country_id,
            'region_id' => $jobUnit->region_id,
            'province_id' => $jobUnit->province_id,
            'city_id' => $jobUnit->city_id,
            'postal_code' => $jobUnit->postal_code,
            'address' => $jobUnit->address,
        ];
    }
}
