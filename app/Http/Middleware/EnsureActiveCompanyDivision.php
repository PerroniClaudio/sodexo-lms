<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompanyDivision
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole('admin') || $user->hasRole('superadmin')) {
            return $next($request);
        }

        $divisionIds = $user->administeredCompanyDivisions()->pluck('company_divisions.id');

        if ($divisionIds->isEmpty()) {
            $request->session()->forget('active_company_division_id');

            return $next($request);
        }

        if ($divisionIds->count() === 1) {
            $request->session()->put('active_company_division_id', $divisionIds->first());

            return $next($request);
        }

        $activeDivisionId = $request->session()->get('active_company_division_id');

        if (! $activeDivisionId || ! $divisionIds->contains((int) $activeDivisionId)) {
            return redirect()->route('company-division.select');
        }

        return $next($request);
    }
}
