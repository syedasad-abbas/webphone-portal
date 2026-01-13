<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class PasswordResetController extends Controller
{
    private function sendOtpEmail(string $email, string $code, ?string $expiresAt): void
    {
        $minutes = 10;
        if ($expiresAt) {
            $expiry = strtotime($expiresAt);
            if ($expiry) {
                $minutes = max(1, (int) ceil(($expiry - time()) / 60));
            }
        }

        $subject = 'Your password reset code';
        $body = "Your reset code is {$code}. It expires in {$minutes} minutes.";

        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    private function showForgotForm(string $role, string $loginRoute)
    {
        return view('auth.forgot-password', [
            'role' => $role,
            'loginRoute' => $loginRoute
        ]);
    }

    private function showResetForm(string $role, string $loginRoute)
    {
        return view('auth.reset-password', [
            'role' => $role,
            'loginRoute' => $loginRoute
        ]);
    }

    private function sendOtp(Request $request, string $role, string $resetRoute)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $headers = [];
        $internalToken = config('services.backend.internal_token');
        if ($internalToken) {
            $headers['x-internal-token'] = $internalToken;
        }

        $response = Http::withHeaders($headers)->post(
            config('services.backend.url') . '/auth/forgot-password',
            [
                'email' => $data['email'],
                'role' => $role
            ]
        );

        if ($response->failed()) {
            return back()->withErrors([
                'email' => 'Unable to send reset code. Try again later.'
            ])->withInput();
        }

        $payload = $response->json();
        if (!empty($payload['code'])) {
            try {
                $this->sendOtpEmail($data['email'], $payload['code'], $payload['expiresAt'] ?? null);
            } catch (\Throwable $err) {
                return back()->withErrors([
                    'email' => 'Unable to send reset code. Check mail configuration.'
                ])->withInput();
            }
        }

        return redirect()->route($resetRoute, ['email' => $data['email']])
            ->with('status', 'If that email exists, a reset code has been sent.');
    }

    private function resetPassword(Request $request, string $role, string $loginRoute)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'min:6', 'confirmed'],
        ]);

        $response = Http::post(config('services.backend.url') . '/auth/reset-password', [
            'email' => $data['email'],
            'role' => $role,
            'code' => $data['code'],
            'password' => $data['password']
        ]);

        if ($response->failed()) {
            return back()->withErrors([
                'code' => 'Invalid or expired code.'
            ])->withInput();
        }

        return redirect()->route($loginRoute)
            ->with('status', 'Password updated. Please sign in.');
    }

    public function showUserForgot()
    {
        return $this->showForgotForm('user', route('user.login'));
    }

    public function showUserReset()
    {
        return $this->showResetForm('user', route('user.login'));
    }

    public function sendUserOtp(Request $request)
    {
        return $this->sendOtp($request, 'user', 'password.reset');
    }

    public function resetUserPassword(Request $request)
    {
        return $this->resetPassword($request, 'user', 'user.login');
    }

    public function showAdminForgot()
    {
        return $this->showForgotForm('admin', route('admin.login'));
    }

    public function showAdminReset()
    {
        return $this->showResetForm('admin', route('admin.login'));
    }

    public function sendAdminOtp(Request $request)
    {
        return $this->sendOtp($request, 'admin', 'admin.password.reset');
    }

    public function resetAdminPassword(Request $request)
    {
        return $this->resetPassword($request, 'admin', 'admin.login');
    }
}
