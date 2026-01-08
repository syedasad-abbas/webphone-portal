<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'WebPhone Portal' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
</head>
<body>
<div class="app-shell">
    <div class="glass-panel">
        <header class="hero">
            <div>
                <p class="badge">Voice Control Center</p>
                <h1>WebPhone Portal</h1>
                <p>{{ $title ?? 'Provision carriers, manage users, and launch calls in one place.' }}</p>
            </div>
            <div class="pill-nav">
                <a href="{{ route('admin.login') }}">Admin</a>
                <a href="{{ route('user.login') }}">Dialer</a>
                @if(session()->has('admin_token'))
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit">Admin Logout</button>
                    </form>
                @elseif(session()->has('user_token'))
                    <form method="POST" action="{{ route('user.logout') }}">
                        @csrf
                        <button type="submit">User Logout</button>
                    </form>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-error">
                <ul style="margin:0; padding-left:1.25rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</div>
</body>
</html>
