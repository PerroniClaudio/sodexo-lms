<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeographicController extends Controller
{
    /**
     * Get all countries with optional search
     */
    public function countries(Request $request)
    {
        $search = $request->get('search');
        $locale = $request->get('locale', 'it');

        $query = DB::table('world_countries as c')
            ->leftJoin('world_countries_locale as cl', function ($join) use ($locale) {
                $join->on('c.id', '=', 'cl.country_id')
                    ->where('cl.locale', '=', $locale);
            })
            ->select(
                'c.id',
                'c.code',
                DB::raw('COALESCE(cl.name, c.name) as name'),
                DB::raw('COALESCE(cl.full_name, c.full_name) as full_name')
            )
            ->orderBy(DB::raw('COALESCE(cl.name, c.name)'));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('c.name', 'LIKE', "%{$search}%")
                    ->orWhere('cl.name', 'LIKE', "%{$search}%")
                    ->orWhere('c.code', 'LIKE', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    /**
     * Get regions/divisions for a specific country
     * @param string|int $countryCodeOrId Country code (e.g. 'it') or country ID
     */
    public function regions(Request $request, $countryCodeOrId)
    {
        $search = $request->get('search');
        $locale = $request->get('locale', 'it');

        // Determina se è un ID o un codice
        if (is_numeric($countryCodeOrId)) {
            $country = DB::table('world_countries')->where('id', $countryCodeOrId)->first();
        } else {
            $country = DB::table('world_countries')->where('code', $countryCodeOrId)->first();
        }

        if (! $country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        $query = DB::table('world_divisions as d')
            ->leftJoin('world_divisions_locale as dl', function ($join) use ($locale) {
                $join->on('d.id', '=', 'dl.division_id')
                    ->where('dl.locale', '=', $locale);
            })
            ->select(
                'd.id',
                'd.code',
                DB::raw('COALESCE(dl.name, d.name) as name')
            )
            ->where('d.country_id', $country->id)
            ->orderBy(DB::raw('COALESCE(dl.name, d.name)'));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('d.name', 'LIKE', "%{$search}%")
                    ->orWhere('dl.name', 'LIKE', "%{$search}%")
                    ->orWhere('d.code', 'LIKE', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    /**
     * Get provinces for a specific region (for countries that have this level)
     */
    public function provinces(Request $request, $regionId)
    {
        $search = $request->get('search');

        $query = DB::table('provinces')
            ->select('id', 'code', 'name')
            ->where('region_id', $regionId)
            ->orderBy('name');

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        return response()->json($query->get());
    }

    /**
     * Get cities for a specific region or province
     */
    public function cities(Request $request, $divisionId)
    {
        $search = $request->get('search');
        $provinceId = $request->get('province_id');

        $query = DB::table('world_cities')
            ->select('id', 'name', 'province_id')
            ->orderBy('name');

        // Se è specificata una provincia, filtra per quella
        if ($provinceId) {
            $query->where('province_id', $provinceId);
        } else {
            // Altrimenti filtra per regione (division_id)
            $query->where('division_id', $divisionId);
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        return response()->json($query->get());
    }

    /**
     * Search all geographic entities (countries, regions, cities) at once
     */
    public function search(Request $request)
    {
        $search = $request->get('q');
        $locale = $request->get('locale', 'it');

        if (! $search || strlen($search) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Search countries
        $countries = DB::table('world_countries as c')
            ->leftJoin('world_countries_locale as cl', function ($join) use ($locale) {
                $join->on('c.id', '=', 'cl.country_id')
                    ->where('cl.locale', '=', $locale);
            })
            ->select(
                'c.id',
                'c.code',
                DB::raw('COALESCE(cl.name, c.name) as name'),
                DB::raw('"country" as type')
            )
            ->where(function ($q) use ($search) {
                $q->where('c.name', 'LIKE', "%{$search}%")
                    ->orWhere('cl.name', 'LIKE', "%{$search}%")
                    ->orWhere('c.code', 'LIKE', "%{$search}%");
            })
            ->limit(5)
            ->get();

        // Search regions
        $regions = DB::table('world_divisions as d')
            ->leftJoin('world_divisions_locale as dl', function ($join) use ($locale) {
                $join->on('d.id', '=', 'dl.division_id')
                    ->where('dl.locale', '=', $locale);
            })
            ->leftJoin('world_countries as c', 'd.country_id', '=', 'c.id')
            ->leftJoin('world_countries_locale as cl', function ($join) use ($locale) {
                $join->on('c.id', '=', 'cl.country_id')
                    ->where('cl.locale', '=', $locale);
            })
            ->select(
                'd.id',
                'd.code',
                DB::raw('COALESCE(dl.name, d.name) as name'),
                DB::raw('COALESCE(cl.name, c.name) as country_name'),
                DB::raw('"region" as type')
            )
            ->where(function ($q) use ($search) {
                $q->where('d.name', 'LIKE', "%{$search}%")
                    ->orWhere('dl.name', 'LIKE', "%{$search}%");
            })
            ->limit(5)
            ->get();

        return response()->json([
            'countries' => $countries,
            'regions' => $regions,
        ]);
    }

    /**
     * Get postal codes for a specific city by ID
     */
    public function postalCodes($cityId)
    {
        $postalCodes = DB::table('postal_codes')
            ->where('city_id', $cityId)
            ->orderBy('postal_code')
            ->pluck('postal_code');

        return response()->json($postalCodes);
    }

    /**
     * Get postal codes for a city by name and country
     */
    public function postalCodesByCity(Request $request)
    {
        $cityName = $request->get('city');
        $cityId = $request->get('city_id');
        $countryCode = $request->get('country', 'it');

        if (! $cityName && ! $cityId) {
            return response()->json([]);
        }

        $query = DB::table('postal_codes as pc')
            ->join('world_cities as wc', 'pc.city_id', '=', 'wc.id')
            ->join('world_countries as c', 'wc.country_id', '=', 'c.id')
            ->where('c.code', $countryCode)
            ->orderBy('pc.postal_code');

        if ($cityId) {
            $query->where('wc.id', $cityId);
        } elseif ($cityName) {
            $query->where('wc.name', 'LIKE', $cityName);
        }

        $postalCodes = $query->pluck('pc.postal_code');

        return response()->json($postalCodes);
    }

    /**
     * Lookup geographic data from postal code (Italy only for now)
     */
    public function lookupPostalCode(Request $request, $postalCode)
    {
        $countryCode = $request->get('country', 'it');

        // Trova il CAP
        $postal = DB::table('postal_codes as pc')
            ->join('world_cities as wc', 'pc.city_id', '=', 'wc.id')
            ->join('world_countries as c', 'wc.country_id', '=', 'c.id')
            ->where('pc.postal_code', $postalCode)
            ->where('c.code', $countryCode)
            ->select(
                'pc.id as postal_code_id',
                'pc.postal_code',
                'wc.id as city_id',
                'wc.name as city_name',
                'wc.division_id as region_id',
                'wc.province_id'
            )
            ->first();

        if (! $postal) {
            return response()->json(['error' => 'Postal code not found'], 404);
        }

        // Carica i dati della regione
        $region = DB::table('world_divisions as d')
            ->leftJoin('world_divisions_locale as dl', function ($join) {
                $join->on('d.id', '=', 'dl.division_id')
                    ->where('dl.locale', '=', 'it');
            })
            ->where('d.id', $postal->region_id)
            ->select(
                'd.id',
                'd.code as region_code',
                DB::raw('COALESCE(dl.name, d.name) as region_name')
            )
            ->first();

        // Carica i dati della provincia (se presente)
        $province = null;
        if ($postal->province_id) {
            $province = DB::table('provinces')
                ->where('id', $postal->province_id)
                ->select('id', 'code', 'name')
                ->first();
        }

        return response()->json([
            'postal_code' => $postal->postal_code,
            'city' => [
                'id' => $postal->city_id,
                'name' => $postal->city_name,
            ],
            'region' => $region ? [
                'id' => $region->id,
                'code' => $region->region_code,
                'name' => $region->region_name,
            ] : null,
            'province' => $province ? [
                'id' => $province->id,
                'code' => $province->code,
                'name' => $province->name,
            ] : null,
        ]);
    }
}
