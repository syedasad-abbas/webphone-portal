@extends('layouts.app')

@section('content')
    <section>
        <header>
            <h2>Create User</h2>
        </header>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <label>Full Name
                <input type="text" name="fullName" value="{{ old('fullName') }}" required>
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
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
            <button type="submit">Create</button>
        </form>
    </section>
@endsection
