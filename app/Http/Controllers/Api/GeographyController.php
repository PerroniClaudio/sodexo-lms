<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Khsing\World\Models\City;
use Khsing\World\Models\Country;
use Khsing\World\Models\Division;

class GeographyController extends Controller
{
    /**
     * Get all countries with search functionality
     */
    public function countries(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $countries = Country::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'full_name', 'code'])
            ->map(function ($country) {
                return [
                    'value' => $country->code,
                    'label' => $country->name,
                    'full_name' => $country->full_name,
                ];
            });

        return response()->json($countries);
    }

    /**
     * Get divisions/states/regions for a country
     */
    public function divisions(Request $request): JsonResponse
    {
        $countryCode = $request->get('country');
        $search = $request->get('search', '');

        if (! $countryCode) {
            return response()->json([]);
        }

        $country = Country::where('code', $countryCode)->first();
        if (! $country) {
            return response()->json([]);
        }

        $divisions = Division::query()
            ->where('country_id', $country->id)
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($division) {
                return [
                    'value' => $division->name,
                    'label' => $division->name,
                    'code' => $division->code,
                    'division_id' => $division->id,
                ];
            });

        return response()->json($divisions);
    }

    /**
     * Get cities for a division/state/region
     */
    public function cities(Request $request): JsonResponse
    {
        $divisionId = $request->get('division');
        $search = $request->get('search', '');

        if (! $divisionId) {
            return response()->json([]);
        }

        $cities = City::query()
            ->where('division_id', $divisionId)
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($city) {
                return [
                    'value' => $city->name,
                    'label' => $city->name,
                ];
            });

        return response()->json($cities);
    }

    /**
     * Get Italian provinces (for Italy specifically)
     */
    public function provinces(Request $request): JsonResponse
    {
        $regionName = $request->get('region');
        $search = $request->get('search', '');

        if (! $regionName) {
            return response()->json([]);
        }

        // Per l'Italia, le province sono un livello aggiuntivo
        $provinces = collect([
            // Lombardia
            ['value' => 'Milano', 'label' => 'Milano', 'region' => 'Lombardia'],
            ['value' => 'Bergamo', 'label' => 'Bergamo', 'region' => 'Lombardia'],
            ['value' => 'Brescia', 'label' => 'Brescia', 'region' => 'Lombardia'],
            ['value' => 'Como', 'label' => 'Como', 'region' => 'Lombardia'],
            ['value' => 'Cremona', 'label' => 'Cremona', 'region' => 'Lombardia'],
            ['value' => 'Mantova', 'label' => 'Mantova', 'region' => 'Lombardia'],
            ['value' => 'Pavia', 'label' => 'Pavia', 'region' => 'Lombardia'],
            ['value' => 'Sondrio', 'label' => 'Sondrio', 'region' => 'Lombardia'],
            ['value' => 'Varese', 'label' => 'Varese', 'region' => 'Lombardia'],
            ['value' => 'Lecco', 'label' => 'Lecco', 'region' => 'Lombardia'],
            ['value' => 'Lodi', 'label' => 'Lodi', 'region' => 'Lombardia'],
            ['value' => 'Monza e Brianza', 'label' => 'Monza e Brianza', 'region' => 'Lombardia'],

            // Lazio
            ['value' => 'Roma', 'label' => 'Roma', 'region' => 'Lazio'],
            ['value' => 'Frosinone', 'label' => 'Frosinone', 'region' => 'Lazio'],
            ['value' => 'Latina', 'label' => 'Latina', 'region' => 'Lazio'],
            ['value' => 'Rieti', 'label' => 'Rieti', 'region' => 'Lazio'],
            ['value' => 'Viterbo', 'label' => 'Viterbo', 'region' => 'Lazio'],

            // Piemonte
            ['value' => 'Torino', 'label' => 'Torino', 'region' => 'Piemonte'],
            ['value' => 'Alessandria', 'label' => 'Alessandria', 'region' => 'Piemonte'],
            ['value' => 'Asti', 'label' => 'Asti', 'region' => 'Piemonte'],
            ['value' => 'Biella', 'label' => 'Biella', 'region' => 'Piemonte'],
            ['value' => 'Cuneo', 'label' => 'Cuneo', 'region' => 'Piemonte'],
            ['value' => 'Novara', 'label' => 'Novara', 'region' => 'Piemonte'],
            ['value' => 'Verbano-Cusio-Ossola', 'label' => 'Verbano-Cusio-Ossola', 'region' => 'Piemonte'],
            ['value' => 'Vercelli', 'label' => 'Vercelli', 'region' => 'Piemonte'],
        ]);

        // Filtra per regione e search
        $filteredProvinces = $provinces
            ->filter(function ($province) use ($regionName, $search) {
                $matchesRegion = $province['region'] === $regionName;
                $matchesSearch = empty($search) ||
                    stripos($province['label'], $search) !== false;

                return $matchesRegion && $matchesSearch;
            })
            ->values();

        return response()->json($filteredProvinces);
    }
}
