@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Enter reset code</h2>
            <p class="card-subtitle">Use the 6-digit code sent to your email to set a new password.</p>
        </header>
        <form method="POST" action="{{ $role === 'admin' ? route('admin.password.update') : route('password.update') }}" class="form-grid">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email', request('email')) }}" required placeholder="you@example.com">
            </label>
            <label>Reset Code
                <input type="text" name="code" value="{{ old('code') }}" required placeholder="123456">
            </label>
            <label>New Password
                <input type="password" name="password" required placeholder="••••••••">
            </label>
            <label>Confirm Password
                <input type="password" name="password_confirmation" required placeholder="••••••••">
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:space-between; gap:1rem;">
                <a href="{{ $loginRoute }}" class="btn btn-ghost" style="text-decoration:none;">Back to login</a>
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
        </form>
    </section>
@endsection
