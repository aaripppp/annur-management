<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Annur Management</title>
    <link rel="icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-on-background antialiased flex items-center justify-center p-6">
    <main class="w-full max-w-md rounded-3xl border border-outline-variant bg-surface p-8 text-center shadow-xl">
        <img src="{{ asset('images/annur_logo2.png') }}" alt="Logo Annur" class="mx-auto h-24 w-24 object-contain">
        <h1 class="mt-6 text-3xl font-bold text-on-surface">Annur Management</h1>
        <p class="mt-2 text-on-surface-variant">Sistem pengelolaan pembayaran Annur.</p>

        @auth
            <a href="{{ route('dashboard') }}" class="mt-8 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-on-primary">
                Buka Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="mt-8 inline-flex rounded-xl bg-primary px-5 py-3 font-semibold text-on-primary">
                Masuk
            </a>
        @endauth
    </main>
</body>
</html>
