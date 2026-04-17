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

        $redirectTo = $user?->hasAnyRole(['admin', 'superadmin'])
            ? route('dashboard')
            : route('reserved-area');

        return redirect()->intended($redirectTo);
    }
}
