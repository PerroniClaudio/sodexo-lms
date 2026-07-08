<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyDivisionSelectionController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->hasRole('admin'), 403);

        $divisions = $user->administeredCompanyDivisions()
            ->orderBy('name')
            ->get(['company_divisions.id', 'company_divisions.name']);

        if ($divisions->count() <= 1) {
            if ($divisions->count() === 1) {
                $request->session()->put('active_company_division_id', $divisions->first()->getKey());
            }

            return redirect()->route('admin.dashboard');
        }

        return view('auth.select-company-division', ['divisions' => $divisions]);
    }

    public function update(Request $request): RedirectResponse
    {
        $divisionId = (int) $request->validate([
            'company_division_id' => ['required', 'integer'],
        ])['company_division_id'];

        abort_unless(
            $request->user()?->administeredCompanyDivisions()->whereKey($divisionId)->exists(),
            403
        );

        $request->session()->put('active_company_division_id', $divisionId);

        return redirect()->route('admin.dashboard');
    }
}
