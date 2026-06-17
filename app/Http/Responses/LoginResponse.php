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

        if ($user?->hasAnyRole(['admin', 'superadmin'])) {
            $redirectTo = route('admin.dashboard');
        } elseif ($user?->hasAnyRole(['teacher', 'docente'])) {
            $redirectTo = route('teacher.courses.index');
        } elseif ($user?->hasRole('tutor')) {
            $redirectTo = route('tutor.dashboard');
        } elseif ($user?->hasRole('user')) {
            $redirectTo = route('user.dashboard');
        } else {
            $redirectTo = route('user.dashboard');
        }

        return redirect()->intended($redirectTo);
    }
}
