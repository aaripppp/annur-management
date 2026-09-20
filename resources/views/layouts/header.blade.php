<!-- TopNavBar -->
@php
    $pageTitle = match(true) {
        request()->routeIs('siswa.*') => 'Siswa',
        request()->routeIs('daycare.*') => 'Daycare',
        request()->routeIs('kelas.*') => 'Data Kelas',
        request()->routeIs('bank.*') => 'Data Bank',
        request()->routeIs('jenis-pembayaran.*') => 'Jenis Pembayaran',
        request()->routeIs('tarif-pembayaran.*') => 'Tarif Pembayaran',
        request()->routeIs('pembayaran.*') => 'Pembayaran',
        request()->routeIs('laporan.*') => 'Laporan',
        request()->routeIs('akun.*') => 'Manajemen Akun',
        default => 'Dashboard',
    };
@endphp

<!-- Desktop TopNavBar (≥lg) -->
<header class="bg-surface-container-lowest dark:bg-inverse-surface text-primary dark:text-primary-fixed font-label-md text-label-md fixed top-0 inset-x-0 ml-sidebar-width h-16 border-b border-outline-variant dark:border-outline shadow-sm flex justify-between items-center px-margin-desktop z-40 hidden lg:flex">
    <div class="font-headline-sm text-headline-sm text-primary flex items-center gap-4">
        {{ $pageTitle }}
    </div>
    <div class="flex items-center gap-4">
        <!-- Profile -->
        <div class="flex items-center gap-3 cursor-pointer hover:bg-surface-container-low dark:hover:bg-on-primary-fixed-variant p-1.5 rounded-lg transition-all">
            <div class="text-right hidden lg:block">
                <p class="font-label-md text-label-md text-on-surface">{{ auth()->user()->name ?? 'Administrator' }}</p>
                <div class="mt-0.5">
                    <span class="bg-primary-fixed text-on-primary-fixed px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                        {{ auth()->user()->roleLabel() }}
                    </span>
                </div>
            </div>
            <div class="w-9 h-9 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold">
                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
            </div>
        </div>
    </div>
</header>

<!-- Mobile TopNavBar (<lg) -->
<header class="bg-surface-container-lowest dark:bg-inverse-surface text-primary dark:text-primary-fixed font-label-md text-label-md fixed top-0 inset-x-0 h-16 border-b border-outline-variant dark:border-outline shadow-sm flex justify-between items-center px-margin-mobile z-40 lg:hidden">
    <div class="flex items-center gap-1 min-w-0">
        <button
            type="button"
            @click="toggleDrawer()"
            :aria-expanded="sidebarOpen ? 'true' : 'false'"
            aria-label="Buka menu navigasi"
            class="-ml-2 p-2 rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-all shrink-0"
        >
            <span class="material-symbols-outlined text-[24px] transition-transform duration-200" :class="sidebarOpen ? 'rotate-90' : ''">menu</span>
        </button>
        <div class="font-headline-sm text-headline-sm text-primary truncate">{{ $pageTitle }}</div>
    </div>
    <div class="flex items-center gap-3 shrink-0">
        <div class="w-9 h-9 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold">
            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
        </div>
    </div>
</header>