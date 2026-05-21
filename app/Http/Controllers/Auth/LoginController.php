<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest;

class LoginController extends AuthenticatedSessionController
{
    public function store(LoginRequest $request)
    {
        return $this->loginPipeline($request)->then(function (LoginRequest $request) {
            $user = $request->user();

            if ($user !== null) {
                DB::table('users_access_log')->insert([
                    'user_id' => $user->getKey(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'logged_in_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return app(LoginResponse::class);
        });
    }
}
