<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.admin-login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $response = Http::post(config('services.backend.url') . '/admin/login', $credentials);

        if ($response->failed()) {
            return back()->withErrors([
                'email' => 'Invalid credentials or backend unavailable.',
            ])->withInput();
        }

        $payload = $response->json();
        $request->session()->put('admin_token', $payload['token']);
        $request->session()->put('admin_user', $payload['user']);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['admin_token', 'admin_user']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
