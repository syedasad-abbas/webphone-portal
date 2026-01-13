@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Reset password</h2>
            <p class="card-subtitle">Enter your email to receive a 6-digit reset code.</p>
        </header>
        <form method="POST" action="{{ $role === 'admin' ? route('admin.password.send') : route('password.send') }}" class="form-grid">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@example.com">
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:space-between; gap:1rem;">
                <a href="{{ $loginRoute }}" class="btn btn-ghost" style="text-decoration:none;">Back to login</a>
                <button type="submit" class="btn btn-primary">Send code</button>
            </div>
        </form>
    </section>
@endsection
