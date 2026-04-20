<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobUnitRequest;
use App\Http\Requests\UpdateJobUnitRequest;
use App\Models\JobUnit;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobUnitController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'name', 'city', 'region', 'country', 'status'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.job-unit.index', [
            'units' => JobUnit::query()
                ->with(['country', 'region', 'province', 'city'])
                ->when($showTrashed, function ($query) {
                    $query->withTrashed();
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%")
                            ->orWhereHas('city', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('region', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('country', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($sort === 'status', function ($query) use ($direction) {
                    // Per status: ordina per deleted_at IS NULL (attivi prima) poi per deleted_at
                    if ($direction === 'asc') {
                        $query->orderByRaw('deleted_at IS NOT NULL ASC, deleted_at ASC');
                    } else {
                        $query->orderByRaw('deleted_at IS NULL ASC, deleted_at DESC');
                    }
                })
                ->when(in_array($sort, ['city', 'region', 'country']), function ($query) use ($sort, $direction) {
                    // Ordinamento per relazioni usando subquery
                    $relationMap = [
                        'city' => ['table' => 'world_cities', 'foreign_key' => 'city_id'],
                        'region' => ['table' => 'world_divisions', 'foreign_key' => 'region_id'],
                        'country' => ['table' => 'world_countries', 'foreign_key' => 'country_id'],
                    ];
                    
                    $relation = $relationMap[$sort];
                    $query->leftJoin($relation['table'], "job_units.{$relation['foreign_key']}", '=', "{$relation['table']}.id")
                        ->orderBy("{$relation['table']}.name", $direction)
                        ->select('job_units.*');
                })
                ->when(!in_array($sort, ['city', 'region', 'country', 'status']), function ($query) use ($sort, $direction) {
                    $query->orderBy($sort, $direction);
                })
                ->paginate(10)
                ->withQueryString(),
            'tableSort' => $sort,
            'tableDirection' => $direction,
            'showTrashed' => $showTrashed,
            'tableSearch' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.job-unit.create');
    }

    public function store(StoreJobUnitRequest $request): RedirectResponse
    {
        $data = $this->convertGeographicNamesToIds($request->validated());
        $unit = JobUnit::query()->create($data);

        return redirect()
            ->route('admin.job-units.edit', $unit)
            ->with('status', __('Unità lavorativa creata con successo.'));
    }

    public function edit(JobUnit $jobUnit): View
    {
        $jobUnit->load(['country', 'region', 'province', 'city']);
        
        return view('admin.job-unit.edit', [
            'unit' => $jobUnit,
        ]);
    }

    public function update(UpdateJobUnitRequest $request, JobUnit $jobUnit): RedirectResponse
    {
        $data = $this->convertGeographicNamesToIds($request->validated());
        $jobUnit->update($data);

        return redirect()
            ->route('admin.job-units.edit', $jobUnit)
            ->with('status', __('Unità lavorativa aggiornata con successo.'));
    }

    public function destroy(JobUnit $jobUnit): RedirectResponse
    {
        // Verifica se l'unità lavorativa è associata a degli utenti
        if ($jobUnit->users()->exists()) {
            return redirect()
                ->route('admin.job-units.edit', $jobUnit)
                ->withErrors(['delete' => __('Impossibile eliminare l\'unità lavorativa: è associata a degli utenti.')]);
        }

        $jobUnit->delete();

        return redirect()
            ->route('admin.job-units.index')
            ->with('status', __('Unità lavorativa eliminata con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $unit = JobUnit::withTrashed()->findOrFail($id);
        $unit->restore();

        return redirect()
            ->route('admin.job-units.index')
            ->with('status', __('Unità lavorativa ripristinata con successo.'));
    }

    /**
     * Converti i nomi geografici in ID per il salvataggio nel database.
     */
    private function convertGeographicNamesToIds(array $data): array
    {
        // Converti country code in country_id
        if (isset($data['country'])) {
            $country = WorldCountry::where('code', $data['country'])->first();
            $data['country_id'] = $country?->id;
            unset($data['country']);
        }

        // Converti region name in region_id
        if (isset($data['region'])) {
            $region = WorldDivision::where('name', $data['region'])->first();
            $data['region_id'] = $region?->id;
            unset($data['region']);
        }

        // Converti province name in province_id
        if (isset($data['province']) && $data['province']) {
            $province = Province::where('name', $data['province'])->first();
            $data['province_id'] = $province?->id;
            unset($data['province']);
        } else {
            $data['province_id'] = null;
            unset($data['province']);
        }

        // Converti city name in city_id
        if (isset($data['city'])) {
            $city = WorldCity::where('name', $data['city'])->first();
            $data['city_id'] = $city?->id;
            unset($data['city']);
        }

        return $data;
    }
}
