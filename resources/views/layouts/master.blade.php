<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Dashboard - Annur Management' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/annur_logo2.png') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,0" rel="stylesheet">

    <!-- Vite Assets (Tailwind CSS v4) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if (! session()->has('sidebar_initialized'))
        @php session()->put('sidebar_initialized', true) @endphp
        <meta name="sidebar-reset" content="1">
    @endif

    @livewireStyles
</head>
<body
    class="bg-background text-on-background font-body-md antialiased min-h-screen overflow-x-clip"
    x-data="sidebarDrawer()"
    x-effect="document.documentElement.style.overflow = (sidebarOpen ? 'hidden' : '')"
    @keydown.escape.window="sidebarOpen && close()"
>

    <!-- Flash Alert -->
    @if(session('success'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            x-init="setTimeout(() => show = false, 3000)"
            class="fixed top-4 right-4 z-[9999] max-w-sm bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 shadow-lg flex items-center gap-3"
        >
            <span class="material-symbols-outlined text-green-600 text-[20px]">check_circle</span>
            <span class="text-body-sm font-label-md flex-1">{{ session('success') }}</span>
            <button @click="show = false" class="text-green-400 hover:text-green-600">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    @endif

    <!-- Sidebar Layout -->
    @include('layouts.sidebar')

    <!-- Header Layout -->
    @include('layouts.header')

    <!-- Main Content Area -->
    <main class="lg:ml-sidebar-width pt-20 lg:pt-24 px-margin-mobile lg:px-margin-desktop pb-margin-mobile lg:pb-margin-desktop min-h-screen flex flex-col gap-stack-lg">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
