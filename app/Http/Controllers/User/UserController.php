<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Support\UserGeographyMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller solo per gestione utenti da frontend (Blade, no API)
 * Gestione del profilo utente autenticato
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserGeographyMapper $userGeographyMapper,
    ) {}

    /**
     * Mostra la pagina di modifica del proprio profilo utente
     */
    public function editOwnProfile(): View
    {
        $user = auth()->user();
        $userRole = $user->roles()->first()?->name;

        // Dato che per ora possono fare le stesse modifiche nel profilo, manteniamo la stess view.
        return view('user.profile.edit', compact('user'));
    }

    /**
     * Aggiorna i dati personali dell'utente autenticato (profilo proprio)
     */
    public function updateOwnProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $userRole = $user->roles()->first()?->name;

        $validated = $request->validate([
            'phone_prefix' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ]);

        // Conversione geografica come in update
        $data = $this->userGeographyMapper->toHomeIds($validated);

        $user->update($data);

        // Reindirizzamento in base al ruolo
        return redirect()->route($userRole . '.profile.edit')->with('status', __('Profilo aggiornato con successo!'));
    }
}
