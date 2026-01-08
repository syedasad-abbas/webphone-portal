<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UserAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.user-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $response = Http::post(config('services.backend.url') . '/auth/login', $credentials);

        if ($response->failed()) {
            return back()->withErrors([
                'email' => 'Invalid credentials or backend unavailable.',
            ])->withInput();
        }

        $payload = $response->json();
        $request->session()->put('user_token', $payload['token']);
        $request->session()->put('portal_user', $payload['user']);

        return redirect()->route('dialer.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['user_token', 'portal_user']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('user.login');
    }
}
