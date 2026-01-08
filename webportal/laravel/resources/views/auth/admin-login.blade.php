@extends('layouts.app')

@section('content')
    <section>
        <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Login as Admin</button>
        </form>
    </section>
@endsection
