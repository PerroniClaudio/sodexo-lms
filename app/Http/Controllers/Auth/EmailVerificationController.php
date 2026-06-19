<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorldCountry;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Show email verification + password setup form
     */
    public function show(Request $request, string $id, string $hash): View|RedirectResponse
    {
        $user = User::findOrFail($id);
        $requiresProfileDetails = $user->hasRole('user');

        // Verifica che l'hash corrisponda
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, __('Link di verifica non valido.'));
        }

        // Verifica che la firma del link sia valida
        if (! $request->hasValidSignature()) {
            abort(403, __('Link di verifica scaduto.'));
        }

        // Se l'email è già verificata E la password è già impostata, redirect al login
        if ($user->hasVerifiedEmail() && $user->hasCompletedProfile()) {
            return redirect()->route('login')->with('status', __('Email già verificata. Puoi effettuare il login.'));
        }

        return view('auth.verify-and-setup', [
            'user' => $user,
            'hash' => $hash,
            'requiresProfileDetails' => $requiresProfileDetails,
            'availableCountries' => $requiresProfileDetails
                ? WorldCountry::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    /**
     * Handle email verification + password setup
     */
    public function store(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        $requiresProfileDetails = $user->hasRole('user');

        // Verifica firma
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, __('Link di verifica non valido.'));
        }

        if (! $request->hasValidSignature()) {
            abort(403, __('Link di verifica scaduto.'));
        }

        // Validazione password
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
            'birth_date' => [$requiresProfileDetails ? 'required' : 'nullable', 'date', 'before:today'],
            'birth_place' => [$requiresProfileDetails ? 'required' : 'nullable', 'string', 'max:255'],
            'citizenship_country_id' => ['nullable', 'integer', 'exists:world_countries,id'],
        ]);

        // Verifica email se non già verificata
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $attributes = [
            'password' => Hash::make($validated['password']),
        ];

        if ($requiresProfileDetails) {
            $attributes['birth_date'] = $validated['birth_date'];
            $attributes['birth_place'] = $validated['birth_place'];
            $attributes['citizenship_country_id'] = $validated['citizenship_country_id'] ?? null;
        }

        $user->forceFill($attributes)->save();

        $user->markProfileAsCompleted();

        return redirect()->route('login')->with('status', __('Account attivato! Ora puoi effettuare il login.'));
    }

    /**
     * Show resend verification email form
     */
    public function resendForm(): RedirectResponse
    {
        return redirect()->route('onboarding.index');
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request): RedirectResponse
    {
        return redirect()->route('onboarding.index');
    }
}
