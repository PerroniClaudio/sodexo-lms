<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();
        $allowedRoles = collect(explode('|', $roles))
            ->filter()
            ->values();

        if (! $user || ! $user->hasAnyRole($allowedRoles->all())) {
            throw UnauthorizedException::forRoles($allowedRoles->all());
        }

        $activeRole = $request->session()->get('active_role');

        if (! $activeRole && $user->getRoleNames()->count() === 1) {
            $activeRole = $user->getRoleNames()->first();
            $request->session()->put('active_role', $activeRole);
        }

        if (! $activeRole || ! $allowedRoles->contains($activeRole)) {
            return redirect()->route('role.select');
        }

        return $next($request);
    }
}
