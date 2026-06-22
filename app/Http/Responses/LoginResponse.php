<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();
        $roles = $user?->getRoleNames() ?? collect();

        if ($roles->count() > 1) {
            $request->session()->forget('active_role');

            return redirect()->route('role.select');
        }

        $activeRole = $roles->first();
        $request->session()->put('active_role', $activeRole);

        if (in_array($activeRole, ['admin', 'superadmin'], true)) {
            $redirectTo = route('admin.dashboard');
        } elseif (in_array($activeRole, ['teacher', 'docente'], true)) {
            $redirectTo = route('teacher.courses.index');
        } elseif ($activeRole === 'tutor') {
            $redirectTo = route('tutor.dashboard');
        } elseif ($activeRole === 'user') {
            $redirectTo = route('user.dashboard');
        } else {
            $redirectTo = route('user.dashboard');
        }

        return redirect()->intended($redirectTo);
    }
}
