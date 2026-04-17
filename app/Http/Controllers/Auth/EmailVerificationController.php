<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * Show email verification + password setup form
     */
    public function show(Request $request, string $id, string $hash): View|RedirectResponse
    {
        $user = User::findOrFail($id);

        // Verifica che l'hash corrisponda
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, __('Link di verifica non valido.'));
        }

        // Verifica che la firma del link sia valida
        if (! $request->hasValidSignature()) {
            abort(403, __('Link di verifica scaduto.'));
        }

        // Se l'email è già verificata E la password è già impostata, redirect al login
        if ($user->hasVerifiedEmail() && $user->password) {
            return redirect()->route('login')->with('status', __('Email già verificata. Puoi effettuare il login.'));
        }

        return view('auth.verify-and-setup', [
            'user' => $user,
            'hash' => $hash,
        ]);
    }

    /**
     * Handle email verification + password setup
     */
    public function store(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Verifica firma
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, __('Link di verifica non valido.'));
        }

        if (! $request->hasValidSignature()) {
            abort(403, __('Link di verifica scaduto.'));
        }

        // Validazione password
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Verifica email se non già verificata
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Imposta password e passa a onboarding
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $user->moveToOnboarding();

        return redirect()->route('login')->with('status', __('Account attivato! Ora puoi effettuare il login.'));
    }

    /**
     * Show resend verification email form
     */
    public function resendForm(): View
    {
        return view('auth.resend-verification');
    }

    /**
     * Resend verification email
     */
    public function resend(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors([
                'email' => __('Nessun utente trovato con questa email.'),
            ]);
        }

        if ($user->hasVerifiedEmail() && $user->password) {
            return back()->with('status', __('Email già verificata. Puoi effettuare il login.'));
        }

        // Invia nuova email di verifica
        $user->sendEmailVerificationNotification();

        return back()->with('status', __('Email di verifica inviata! Controlla la tua casella di posta.'));
    }
}
