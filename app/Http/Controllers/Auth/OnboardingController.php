<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    /**
     * Show profile completion form
     */
    public function show(Request $request): View
    {
        return view('auth.complete-profile', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Handle profile completion
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone_prefix' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:M,F'],
            'nation' => ['nullable', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $request->user();

        // Conversione campi geografici in ID
        $country = null;
        $region = null;
        $province = null;
        $city = null;

        if (!empty($validated['country'])) {
            $country = \App\Models\WorldCountry::where('code', $validated['country'])->first();
        }
        if (!empty($validated['region'])) {
            $region = \App\Models\WorldDivision::where('name', $validated['region'])->first();
        }
        if (!empty($validated['province'])) {
            $province = \App\Models\Province::where('code', $validated['province'])
                ->orWhere('name', $validated['province'])->first();
        }
        if (!empty($validated['city'])) {
            $city = \App\Models\WorldCity::where('name', $validated['city'])->first();
        }

        $user->update([
            ...$validated,
            'home_country_id' => $country?->id,
            'home_region_id' => $region?->id,
            'home_province_id' => $province?->id,
            'home_city_id' => $city?->id,
        ]);
        $user->markProfileAsCompleted();

        // Redirect in base al ruolo
        if ($user->hasRole('user')) {
            return redirect()->route('user.courses.index')->with('status', __('Profilo completato con successo!'));
        }
        if ($user->hasRole('admin') || $user->hasRole('superadmin')) {
            return redirect()->route('admin.courses.index')->with('status', __('Profilo completato con successo!'));
        }
        // fallback: home o altra pagina neutra
        return redirect('/')->with('status', __('Profilo completato con successo!'));
    }
}
