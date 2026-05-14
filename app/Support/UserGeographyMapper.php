<?php

namespace App\Support;

use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;

class UserGeographyMapper
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toHomeIds(array $data): array
    {
        if (isset($data['country'])) {
            $country = WorldCountry::query()->where('code', $data['country'])->first();
            $data['home_country_id'] = $country?->id;
            unset($data['country']);
        }

        if (isset($data['region'])) {
            $region = WorldDivision::query()->where('name', $data['region'])->first();
            $data['home_region_id'] = $region?->id;
            unset($data['region']);
        }

        if (isset($data['province']) && $data['province']) {
            $province = Province::query()->where('name', $data['province'])->first();
            $data['home_province_id'] = $province?->id;
            unset($data['province']);
        } else {
            $data['home_province_id'] = null;
            unset($data['province']);
        }

        if (isset($data['city'])) {
            $city = WorldCity::query()->where('name', $data['city'])->first();
            $data['home_city_id'] = $city?->id;
            unset($data['city']);
        }

        return $data;
    }
}
