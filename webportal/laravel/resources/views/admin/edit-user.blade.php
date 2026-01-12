@extends('layouts.app')

@section('content')
    <section class="card">
        <header>
            <h2 class="card-title">Edit user</h2>
            <p class="card-subtitle">Update profile details, carrier assignment, and permissions.</p>
        </header>
        <form method="POST" action="{{ route('admin.users.update', $user['id']) }}" class="form-grid">
            @csrf
            @method('PUT')
            <label>Full Name
                <input type="text" name="fullName" value="{{ old('fullName', $user['full_name'] ?? '') }}" required>
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email', $user['email'] ?? '') }}" required>
            </label>
            <label>Password (leave blank to keep)
                <input type="password" name="password" placeholder="Optional new password">
            </label>
            <label>Group
                <select name="groupId">
                    <option value="">Default</option>
                    @foreach($groups as $group)
                        <option value="{{ $group['id'] }}" {{ ($user['group_id'] ?? '') === $group['id'] ? 'selected' : '' }}>
                            {{ $group['name'] }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Carrier
                <select name="carrierId">
                    <option value="">Default</option>
                    @foreach($carriers as $carrier)
                        <option value="{{ $carrier['id'] }}" {{ ($user['carrier_id'] ?? '') === $carrier['id'] ? 'selected' : '' }}>
                            {{ $carrier['name'] }}
                        </option>
                    @endforeach
                </select>
            </label>
            @php($recordingEnabled = old('recordingEnabled', $user['recording_enabled'] ?? false))
            <label style="grid-column:1/-1; flex-direction:row; align-items:center; gap:0.6rem;">
                <input type="hidden" name="recordingEnabled" value="0">
                <input type="checkbox" name="recordingEnabled" value="1" {{ $recordingEnabled ? 'checked' : '' }}>
                Recording enabled
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:space-between; gap:1rem;">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary">Update user</button>
            </div>
        </form>
    </section>
@endsection
