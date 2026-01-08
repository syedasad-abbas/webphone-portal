<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    protected function loadReferenceData(string $token)
    {
        $groups = Http::withToken($token)->get(config('services.backend.url') . '/admin/groups')->collect();
        $carriers = Http::withToken($token)->get(config('services.backend.url') . '/admin/carriers')->collect();

        return [$groups, $carriers];
    }

    public function create(Request $request)
    {
        $token = $request->session()->get('admin_token');
        [$groups, $carriers] = $token ? $this->loadReferenceData($token) : [collect(), collect()];

        return view('admin.create-user', compact('groups', 'carriers'));
    }

    public function store(Request $request)
    {
        $token = $request->session()->get('admin_token');
        $data = $request->validate([
            'fullName' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
            'groupId' => ['nullable', 'string'],
            'carrierId' => ['nullable', 'string'],
        ]);

        $response = Http::withToken($token)->post(config('services.backend.url') . '/admin/users', $data);

        if ($response->failed()) {
            return back()->withErrors([
                'email' => 'Unable to create user, please verify the data.',
            ])->withInput();
        }

        return redirect()->route('admin.dashboard')->with('status', 'User created successfully.');
    }

    public function edit(Request $request, string $userId)
    {
        $token = $request->session()->get('admin_token');
        $userResponse = Http::withToken($token)->get(config('services.backend.url') . "/admin/users/{$userId}");

        if ($userResponse->failed()) {
            return redirect()->route('admin.dashboard')->withErrors('User not found.');
        }

        [$groups, $carriers] = $this->loadReferenceData($token);

        return view('admin.edit-user', [
            'user' => $userResponse->json(),
            'groups' => $groups,
            'carriers' => $carriers,
        ]);
    }

    public function update(Request $request, string $userId)
    {
        $token = $request->session()->get('admin_token');
        $data = $request->validate([
            'fullName' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['nullable', 'min:6'],
            'groupId' => ['nullable', 'string'],
            'carrierId' => ['nullable', 'string'],
            'recordingEnabled' => ['nullable', 'boolean'],
        ]);

        $payload = $data;
        if (empty($payload['password'])) {
            unset($payload['password']);
        }
        if (array_key_exists('recordingEnabled', $payload)) {
            $payload['recordingEnabled'] = $request->boolean('recordingEnabled');
        }

        $response = Http::withToken($token)->put(config('services.backend.url') . "/admin/users/{$userId}", $payload);

        if ($response->failed()) {
            return back()->withErrors([
                'email' => 'Unable to update user.',
            ])->withInput();
        }

        return redirect()->route('admin.dashboard')->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, string $userId)
    {
        $token = $request->session()->get('admin_token');
        $response = Http::withToken($token)->delete(config('services.backend.url') . "/admin/users/{$userId}");

        if ($response->failed()) {
            return redirect()->route('admin.dashboard')->withErrors('Unable to delete user.');
        }

        return redirect()->route('admin.dashboard')->with('status', 'User deleted.');
    }
}
