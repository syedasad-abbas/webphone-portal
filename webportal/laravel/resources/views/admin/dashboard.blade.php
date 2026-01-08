@extends('layouts.app')

@section('content')
    <section>
        <header>
            <h2>Provisioned Users</h2>
        </header>
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Group</th>
                <th>Carrier</th>
                <th>Recording</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user['full_name'] }}</td>
                    <td>{{ $user['email'] }}</td>
                    <td>{{ $user['group_name'] ?? 'Default' }}</td>
                    <td>{{ $user['carrier_name'] ?? 'Default' }}</td>
                    <td>{{ $user['recording_enabled'] ? 'Enabled' : 'Disabled' }}</td>
                    <td>
                        <a href="{{ route('admin.users.edit', $user['id']) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.users.destroy', $user['id']) }}" style="display:inline" onsubmit="return confirm('Delete this user?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="secondary" style="margin-left:0.5rem;">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <a href="{{ route('admin.users.create') }}">Create User</a> |
        <a href="{{ route('admin.carriers.index') }}">Manage Carriers</a>

        <form method="POST" action="{{ route('admin.logout') }}" style="margin-top: 1rem;">
            @csrf
            <button type="submit">Logout</button>
        </form>
    </section>
@endsection
