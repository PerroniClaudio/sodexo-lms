<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se non c'è utente autenticato, lascia passare (gestito da auth middleware)
        if (! $user) {
            return $next($request);
        }

        // Account sospeso - blocca l'accesso
        if ($user->isBlocked()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Il tuo account è stato sospeso. Contatta l\'amministratore.');
        }

        // Se l'utente ha bisogno di onboarding e non è già sulla route di onboarding
        if ($user->needsOnboarding() && ! $request->routeIs('onboarding.*')) {
            return redirect()->route('onboarding.index');
        }

        // Se l'utente ha bisogno di aggiornare i dati e non è sulla route di aggiornamento
        if ($user->needsDataUpdate() && ! $request->routeIs('profile.update')) {
            return redirect()->route('profile.update')
                ->with('warning', 'È richiesto un aggiornamento dei tuoi dati. Completa il profilo per continuare.');
        }

        return $next($request);
    }
}
