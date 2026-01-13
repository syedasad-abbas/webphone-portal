<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DialerController extends Controller
{
    public function index()
    {
        if (!session()->has('user_token')) {
            return redirect()->route('user.login');
        }

        return view('dialer.index');
    }

    public function dial(Request $request)
    {
        if (!$request->session()->has('user_token')) {
            return response()->json(['message' => 'You must be logged in to place calls.'], 401);
        }

        $data = $request->validate([
            'destination' => ['required', 'string'],
            'callerId' => ['nullable', 'string'],
        ]);
        if (empty(trim($data['callerId'] ?? ''))) {
            $data['callerId'] = null;
        }

        try {
            $response = Http::withToken($request->session()->get('user_token'))
                ->timeout(5)
                ->post(config('services.backend.url') . '/calls', $data);
        } catch (\Throwable $th) {
            $message = 'Backend unavailable, please try again.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }
            return back()->withErrors(['destination' => $message]);
        }

        if ($response->failed()) {
            $message = 'Unable to queue call. ' . $response->json('message', 'Backend unavailable.');
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors([
                'destination' => $message,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($response->json());
        }

        return back()->with('status', 'Call queued successfully.');
    }

    public function session(Request $request, string $uuid)
    {
        return view('dialer.session', ['callUuid' => $uuid]);
    }

    public function callStatus(Request $request, string $uuid)
    {
        try {
            $response = Http::withToken($request->session()->get('user_token'))
                ->timeout(5)
                ->get(config('services.backend.url') . '/calls/' . $uuid);
            if ($response->failed()) {
                return response()->json(['status' => 'ended'], $response->status());
            }
            return response()->json($response->json());
        } catch (\Throwable $th) {
            return response()->json(['status' => 'ended'], 503);
        }
    }

    public function control(Request $request, string $uuid, string $action)
    {
        $allowed = ['mute', 'unmute', 'hangup'];
        if (!in_array($action, $allowed, true)) {
            abort(404);
        }
        try {
            $response = Http::withToken($request->session()->get('user_token'))
                ->timeout(5)
                ->post(config('services.backend.url') . "/calls/{$uuid}/{$action}");
            if ($response->failed()) {
                return response()->json(['message' => $response->json('message', 'Unable to update call.')], $response->status());
            }
            return response()->json($response->json());
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Backend unavailable.'], 503);
        }
    }
}
