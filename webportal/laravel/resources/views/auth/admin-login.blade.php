@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Administrator Access</h2>
            <p class="card-subtitle">Securely manage users, groups, and carriers.</p>
        </header>
        <form method="POST" action="{{ route('admin.login.submit') }}" class="form-grid">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="admin@webphone.com">
            </label>
            <label>Password
                <input type="password" name="password" required placeholder="••••••••">
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Sign in</button>
            </div>
        </form>
    </section>
@endsection
