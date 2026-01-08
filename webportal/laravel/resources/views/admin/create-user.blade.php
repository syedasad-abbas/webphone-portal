@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Create user</h2>
            <p class="card-subtitle">Assign permission groups, carriers, and temporary credentials.</p>
        </header>
        <form method="POST" action="{{ route('admin.users.store') }}" class="form-grid">
            @csrf
            <label>Full Name
                <input type="text" name="fullName" value="{{ old('fullName') }}" required placeholder="Jane Smith">
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required placeholder="user@example.com">
            </label>
            <label>Password
                <input type="password" name="password" required placeholder="Temporary password">
            </label>
            <label>Group
                <select name="groupId">
                    <option value="">Default</option>
                    @foreach($groups as $group)
                        <option value="{{ $group['id'] }}">{{ $group['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>Carrier
                <select name="carrierId">
                    <option value="">Default</option>
                    @foreach($carriers as $carrier)
                        <option value="{{ $carrier['id'] }}">{{ $carrier['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:space-between; gap:1rem;">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary">Create user</button>
            </div>
        </form>
    </section>
@endsection
