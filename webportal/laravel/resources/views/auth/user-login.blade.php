@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Dialer Login</h2>
            <p class="card-subtitle">Enter your user credentials to launch the WebPhone experience.</p>
        </header>
        <form method="POST" action="{{ route('user.login.submit') }}" class="form-grid">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">
            </label>
            <label>Password
                <input type="password" name="password" required placeholder="••••••••">
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:space-between; gap:1rem;">
                <a href="{{ route('password.forgot') }}" class="btn btn-ghost" style="text-decoration:none;">Forgot password?</a>
                <button type="submit" class="btn btn-primary">Enter dialer</button>
            </div>
        </form>
    </section>
@endsection
