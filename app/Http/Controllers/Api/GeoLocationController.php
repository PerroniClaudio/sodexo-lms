<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoLocationController extends Controller
{
    /**
     * Get all countries.
     */
    public function countries(): JsonResponse
    {
        $countries = WorldCountry::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($country) => [
                'value' => $country->code,
                'label' => $country->name,
            ]);

        return response()->json($countries);
    }

    /**
     * Get regions (divisions) for a specific country.
     */
    public function regions(Request $request): JsonResponse
    {
        $countryCode = $request->query('country');

        if (! $countryCode) {
            return response()->json(['error' => 'Country code is required'], 400);
        }

        $country = WorldCountry::query()
            ->where('code', $countryCode)
            ->first();

        if (! $country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $regions = WorldDivision::query()
            ->where('country_id', $country->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($region) => [
                'value' => $region->name,
                'label' => $region->name,
            ]);

        return response()->json($regions);
    }

    /**
     * Get provinces for a specific region (only for Italy).
     */
    public function provinces(Request $request): JsonResponse
    {
        $countryCode = $request->query('country');
        $regionName = $request->query('region');

        if (! $countryCode || ! $regionName) {
            return response()->json(['error' => 'Country and region are required'], 400);
        }

        $country = WorldCountry::query()
            ->where('code', $countryCode)
            ->first();

        if (! $country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $region = WorldDivision::query()
            ->where('country_id', $country->id)
            ->where('name', $regionName)
            ->first();

        if (! $region) {
            return response()->json(['error' => 'Region not found'], 404);
        }

        $provinces = Province::query()
            ->where('region_id', $region->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($province) => [
                'value' => $province->name,
                'label' => $province->name,
            ]);

        return response()->json($provinces);
    }

    /**
     * Get cities for a specific region or province.
     */
    public function cities(Request $request): JsonResponse
    {
        $countryCode = $request->query('country');
        $regionName = $request->query('region');
        $provinceName = $request->query('province');

        if (! $countryCode || ! $regionName) {
            return response()->json(['error' => 'Country and region are required'], 400);
        }

        $country = WorldCountry::query()
            ->where('code', $countryCode)
            ->first();

        if (! $country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $region = WorldDivision::query()
            ->where('country_id', $country->id)
            ->where('name', $regionName)
            ->first();

        if (! $region) {
            return response()->json(['error' => 'Region not found'], 404);
        }

        // Se c'è una provincia, filtra per quella
        if ($provinceName) {
            $province = Province::query()
                ->where('region_id', $region->id)
                ->where('name', $provinceName)
                ->first();

            if (! $province) {
                return response()->json(['error' => 'Province not found'], 404);
            }

            $cities = WorldCity::query()
                ->where('province_id', $province->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($city) => [
                    'value' => $city->name,
                    'label' => $city->name,
                ]);
        } else {
            // Altrimenti recupera tutte le città della regione
            $cities = WorldCity::query()
                ->where('division_id', $region->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($city) => [
                    'value' => $city->name,
                    'label' => $city->name,
                ]);
        }

        return response()->json($cities);
    }
}
