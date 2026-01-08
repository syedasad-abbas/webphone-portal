<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebPhone Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
</head>
<body>
<main class="container">
    <header style="margin: 2rem 0;">
        <h1>WebPhone Portal</h1>
        @isset($title)
            <p>{{ $title }}</p>
        @endisset
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            @if(session()->has('admin_token'))
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="secondary">Admin Logout</button>
                </form>
            @endif
            @if(session()->has('user_token'))
                <form method="POST" action="{{ route('user.logout') }}">
                    @csrf
                    <button type="submit" class="secondary">Logout</button>
                </form>
            @endif
        </div>
    </header>
    @if (session('status'))
        <article class="success">{{ session('status') }}</article>
    @endif
    @if ($errors->any())
        <article class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </article>
    @endif
    @yield('content')
</main>
</body>
</html>
