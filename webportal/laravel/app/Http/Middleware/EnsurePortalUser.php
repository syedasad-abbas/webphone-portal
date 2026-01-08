<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePortalUser
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('user_token')) {
            return redirect()->route('user.login');
        }

        return $next($request);
    }
}
