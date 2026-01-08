<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $token = $request->session()->get('admin_token');

        $users = collect();
        if ($token) {
            $response = Http::withToken($token)->get(config('services.backend.url') . '/admin/users');
            if ($response->ok()) {
                $users = collect($response->json());
            }
        }

        return view('admin.dashboard', [
            'users' => $users,
        ]);
    }
}
