<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GoPasig')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pasig_logo.png') }}">
</head>
<body>
    @include('components.navbar')

    <main>
        @yield('content')
    </main>

    <footer>
        <p>&copy; {{ date('Y') }} GoPasig</p>
    </footer>
</body>
</html>
