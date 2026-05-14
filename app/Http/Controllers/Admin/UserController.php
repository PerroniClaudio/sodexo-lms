<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\JobUnit;
use App\Models\Province;
use App\Models\User;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use App\Support\UserGeographyMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Controller solo per gestione utenti da backend (Blade, no API)
 * Ricerca, ordinamento e paginazione su nome, cognome, CF, email
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserGeographyMapper $userGeographyMapper,
    ) {}

    public function index(Request $request): View
    {
        $query = User::query()->with('jobRole');

        // Mostra eliminati
        $showTrashed = $request->boolean('show_trashed');
        if ($showTrashed) {
            $query->withTrashed();
        }

        // Ricerca
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('surname', 'like', "%$search%")
                    ->orWhere('fiscal_code', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        // Ordinamento
        $sortable = ['name', 'surname', 'fiscal_code', 'email', 'account_type', 'role', 'status'];
        $sort = $request->input('sort', 'surname');
        $direction = $request->input('direction', 'asc');
        if (! in_array($sort, $sortable)) {
            $sort = 'surname';
        }
        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        if ($sort === 'status') {
            // Ordinamento per stato: attivo (non eliminato) prima di eliminato
            $users = $query->get()->sortBy(function ($user) {
                return $user->trashed() ? 1 : 0;
            }, SORT_REGULAR, $direction === 'desc');
            // Pagina manualmente la Collection
            $perPage = 20;
            $page = $request->input('page', 1);
            $paged = $users->slice(($page - 1) * $perPage, $perPage)->values();
            $users = new LengthAwarePaginator($paged, $users->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } elseif ($sort === 'account_type') {
            // Ordina per ruolo Spatie (primo ruolo alfabetico) lato PHP dopo la query
            $users = $query->get()->sortBy(function ($user) {
                return $user->getRoleNames()->first() ?? '';
            }, SORT_REGULAR, $direction === 'desc');
            // Pagina manualmente la Collection
            $perPage = 20;
            $page = $request->input('page', 1);
            $paged = $users->slice(($page - 1) * $perPage, $perPage)->values();
            $users = new LengthAwarePaginator($paged, $users->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } elseif ($sort === 'role') {
            // Ordina per nome ruolo tabellare
            $query->leftJoin('job_roles', 'users.job_role_id', '=', 'job_roles.id')
                ->orderBy('job_roles.name', $direction)
                ->select('users.*');
            $users = $query->paginate(20)->appends($request->only(['search', 'sort', 'direction', 'show_trashed']));
        } else {
            $query->orderBy($sort, $direction);
            $users = $query->paginate(20)->appends($request->only(['search', 'sort', 'direction', 'show_trashed']));
        }

        return view('admin.users.index', compact('users', 'sort', 'direction', 'search', 'showTrashed'));
    }

    public function create(): View
    {
        $jobCategories = JobCategory::all();
        $jobLevels = JobLevel::all();
        $jobTitles = JobTitle::all();
        $jobRoles = JobRole::all();
        $jobSectors = JobSector::all();
        $jobUnits = JobUnit::all();

        return view('admin.users.create', compact('jobCategories', 'jobLevels', 'jobTitles', 'jobRoles', 'jobSectors', 'jobUnits'));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $this->userGeographyMapper->toHomeIds($request->validated());
        $accountType = $data['account_type'] ?? 'user';
        unset($data['account_type']);

        // Normalizza i campi opzionali a null se stringa vuota
        foreach (['job_category_id', 'job_level_id'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // Controllo coerenza geografica
        $geoConsistent = $this->checkGeographicConsistency(
            $data['home_city_id'] ?? null,
            $data['home_province_id'] ?? null,
            $data['home_region_id'] ?? null,
            $data['home_country_id'] ?? null
        );
        if ($geoConsistent === false) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['geography' => 'Attenzione: città, provincia, regione e nazione non sono coerenti tra loro.']);
        }

        // Crea utente
        $user = User::create($data);
        // Assegna ruolo Spatie
        $user->assignRole($accountType);

        return redirect()->route('admin.users.index')->with('success', 'Utente creato con successo');
    }

    public function edit(User $user): View
    {
        $user->load('roles', 'homeCountry', 'homeRegion', 'homeProvince', 'homeCity');
        $jobCategories = JobCategory::all();
        $jobLevels = JobLevel::all();
        $jobTitles = JobTitle::all();
        $jobRoles = JobRole::all();
        $jobSectors = JobSector::all();
        $jobUnits = JobUnit::all();

        return view('admin.users.edit', compact('user', 'jobCategories', 'jobLevels', 'jobTitles', 'jobRoles', 'jobSectors', 'jobUnits'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $this->userGeographyMapper->toHomeIds($request->validated());
        $accountType = $data['account_type'] ?? $user->getRoleNames()->first() ?? 'user';
        unset($data['account_type']);

        // Normalizza i campi opzionali a null se stringa vuota
        foreach (['job_category_id', 'job_level_id'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // Controllo coerenza geografica
        $geoConsistent = $this->checkGeographicConsistency(
            $data['home_city_id'] ?? null,
            $data['home_province_id'] ?? null,
            $data['home_region_id'] ?? null,
            $data['home_country_id'] ?? null
        );
        if ($geoConsistent === false) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['geography' => 'Attenzione: città, provincia, regione e nazione non sono coerenti tra loro.']);
        }

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        $user->syncRoles([$accountType]);

        return redirect()->route('admin.users.index')->with('success', 'Utente aggiornato con successo');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Utente eliminato con successo');
    }

    public function restore($id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('admin.users.index')->with('success', 'Utente ripristinato con successo');
    }

    /**
     * Verifica la coerenza tra città, provincia, regione e nazione (solo per i valori inseriti).
     * Restituisce true se coerenti, false se incoerenti, null se non verificabile.
     */
    private function checkGeographicConsistency(?int $cityId, ?int $provinceId, ?int $regionId, ?int $countryId): ?bool
    {
        // Se nessun valore è inserito, non verificabile
        if (! $cityId && ! $provinceId && ! $regionId && ! $countryId) {
            return null;
        }

        $city = $cityId ? WorldCity::find($cityId) : null;
        $province = $provinceId ? Province::find($provinceId) : null;
        $region = $regionId ? WorldDivision::find($regionId) : null;
        $country = $countryId ? WorldCountry::find($countryId) : null;

        // Verifica coerenza città-provincia
        if ($city && $province && $city->province_id !== $province->id) {
            return false;
        }

        // Verifica coerenza provincia-regione
        if ($province && $region && $province->region_id !== $region->id) {
            return false;
        }

        // Verifica coerenza regione-nazione
        if ($region && $country && $region->country_id !== $country->id) {
            return false;
        }

        // Verifica coerenza città-regione (se manca la provincia)
        if ($city && $region && $city->region_id && $city->region_id !== $region->id) {

            return false;
        }

        // Verifica coerenza città-nazione (se manca la provincia e la regione)
        if ($city && $country && $city->country_id && $city->country_id !== $country->id) {
            return false;
        }

        // Se tutte le verifiche passano o non sono verificabili, ritorna true
        return true;
    }
}
