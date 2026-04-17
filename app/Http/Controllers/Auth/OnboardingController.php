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
            'province' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $request->user();
        $user->update($validated);
        $user->markProfileAsCompleted();

        return redirect()->route('admin.courses.index')->with('status', __('Profilo completato con successo!'));
    }
}
