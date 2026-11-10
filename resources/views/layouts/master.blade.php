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
    @php
        $profileNotification = session('profile_notification');
        $flashType = is_array($profileNotification)
            ? ($profileNotification['type'] ?? null)
            : (session('success') ? 'success' : null);
        $flashMessage = is_array($profileNotification)
            ? ($profileNotification['message'] ?? null)
            : session('success');
        $isErrorFlash = $flashType === 'error';
    @endphp
        <div
            data-flash-toast
            data-flash-type="{{ $flashType }}"
            x-data="{
                show: @js((bool) $flashMessage),
                type: @js($flashType ?? 'success'),
                message: @js($flashMessage ?? ''),
                timer: null,
                notify(detail) {
                    clearTimeout(this.timer);
                    this.type = detail.type === 'error' ? 'error' : 'success';
                    this.message = detail.message ?? '';
                    this.show = this.message !== '';
                    this.timer = setTimeout(() => this.show = false, this.type === 'error' ? 5000 : 3000);
                }
            }"
            x-on:annur-toast.window="notify($event.detail)"
            x-show="show"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            @if($flashMessage) x-init="setTimeout(() => show = false, {{ $isErrorFlash ? 5000 : 3000 }})" @endif
            class="fixed top-4 right-4 z-[9999] max-w-sm border rounded-xl px-4 py-3 shadow-lg flex items-center gap-3"
            x-bind:class="type === 'error' ? 'bg-error-container border-error text-on-error-container' : 'bg-green-50 border-green-200 text-green-800'"
            x-bind:role="type === 'error' ? 'alert' : 'status'"
            x-bind:aria-live="type === 'error' ? 'assertive' : 'polite'"
        >
            <span class="material-symbols-outlined text-[20px]" x-bind:class="type === 'error' ? 'text-error' : 'text-green-600'" x-text="type === 'error' ? 'error' : 'check_circle'" aria-hidden="true">{{ $isErrorFlash ? 'error' : 'check_circle' }}</span>
            <span class="text-body-sm font-label-md flex-1" x-text="message">{{ $flashMessage }}</span>
            <button type="button" @click="show = false" x-bind:class="type === 'error' ? 'text-error' : 'text-green-400 hover:text-green-600'" aria-label="Tutup notifikasi">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">close</span>
            </button>
        </div>

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
