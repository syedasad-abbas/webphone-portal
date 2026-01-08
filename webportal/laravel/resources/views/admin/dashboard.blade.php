@extends('layouts.app')

@section('content')
    <section class="card">
        <header class="hero" style="margin-bottom:1rem;">
            <div>
                <h2 class="card-title">Provisioned Users</h2>
                <p class="card-subtitle">Manage access, carriers, and recording policies.</p>
            </div>
            <div class="pill-nav">
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary" style="text-decoration:none;">Create user</a>
                <a href="{{ route('admin.carriers.index') }}" class="btn btn-ghost" style="text-decoration:none;">Carriers</a>
            </div>
        </header>

        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Group</th>
                <th>Carrier</th>
                <th>Recording</th>
                <th style="text-align:right;">Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user['full_name'] }}</td>
                    <td>{{ $user['email'] }}</td>
                    <td><span class="badge">{{ $user['group_name'] ?? 'Default' }}</span></td>
                    <td><span class="badge">{{ $user['carrier_name'] ?? 'Default' }}</span></td>
                    <td>
                        <span class="badge {{ $user['recording_enabled'] ? 'badge-success' : 'badge-danger' }}">
                            {{ $user['recording_enabled'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <a href="{{ route('admin.users.edit', $user['id']) }}" class="btn btn-ghost" style="margin-right:0.35rem;">Edit</a>
                        <form method="POST" action="{{ route('admin.users.destroy', $user['id']) }}" style="display:inline" onsubmit="return confirm('Delete this user?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
