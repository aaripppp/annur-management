<!-- TopNavBar -->
@php
    $pageTitle = match(true) {
        request()->routeIs('siswa.*') => 'Siswa',
        request()->routeIs('daycare.*') => 'Daycare',
        request()->routeIs('kelas.*') => 'Data Kelas',
        request()->routeIs('bank.*') => 'Data Bank',
        request()->routeIs('jenis-pembayaran.*') => 'Jenis Pembayaran',
        request()->routeIs('kategori-siswa.*') => 'Kategori Siswa',
        request()->routeIs('tarif-pembayaran.*') => 'Tarif Pembayaran',
        request()->routeIs('pembayaran.*') => 'Pembayaran',
        request()->routeIs('laporan.*') => 'Laporan',
        request()->routeIs('akun.*') => 'Manajemen Akun',
        request()->routeIs('profile.*') => 'Profil Saya',
        default => 'Dashboard',
    };
@endphp

<!-- Desktop TopNavBar (≥lg) -->
<header class="bg-surface-container-lowest dark:bg-inverse-surface text-primary dark:text-primary-fixed font-label-md text-label-md fixed top-0 inset-x-0 ml-sidebar-width h-16 border-b border-outline-variant dark:border-outline shadow-sm flex justify-between items-center px-margin-desktop z-40 hidden lg:flex">
    <div class="font-headline-sm text-headline-sm text-primary flex items-center gap-4">
        {{ $pageTitle }}
    </div>
    <div
        class="relative"
        x-data="{ open: false }"
        @click.outside="open = false"
        @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }"
    >
        <button
            type="button"
            x-ref="trigger"
            @click="open = ! open"
            @keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.profileLink.focus())"
            @keydown.arrow-up.prevent="open = true; $nextTick(() => $refs.logoutButton.focus())"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-controls="desktop-account-menu"
            class="flex items-center gap-3 rounded-lg p-1.5 transition-all hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-secondary dark:hover:bg-on-primary-fixed-variant"
        >
            <span class="text-right">
                <span class="block font-label-md text-label-md text-on-surface">{{ auth()->user()->name ?? 'Administrator' }}</span>
                <span class="mt-0.5 block">
                    <span class="rounded-full bg-primary-fixed px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-on-primary-fixed">
                        {{ auth()->user()->roleLabel() }}
                    </span>
                </span>
            </span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary-container font-bold text-on-secondary-container">
                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
            </span>
            <span class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
        </button>

        <div
            id="desktop-account-menu"
            x-cloak
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            role="menu"
            class="absolute right-0 top-full mt-2 w-52 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest p-1.5 text-on-surface shadow-lg"
        >
            <a
                x-ref="profileLink"
                href="{{ route('profile.edit') }}"
                role="menuitem"
                @click="open = false"
                @keydown.arrow-down.prevent="$refs.logoutButton.focus()"
                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-body-md transition-colors hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none"
            >
                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">person</span>
                Profil Saya
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" x-ref="logoutButton" role="menuitem" @keydown.arrow-up.prevent="$refs.profileLink.focus()" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-body-md transition-colors hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none">
                    <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">logout</span>
                    Keluar
                </button>
            </form>
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
    <div
        class="relative shrink-0"
        x-data="{ open: false }"
        @click.outside="open = false"
        @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }"
    >
        <button
            type="button"
            x-ref="trigger"
            @click="open = ! open"
            @keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.profileLink.focus())"
            @keydown.arrow-up.prevent="open = true; $nextTick(() => $refs.logoutButton.focus())"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-controls="mobile-account-menu"
            aria-label="Buka menu akun"
            class="flex h-10 w-10 items-center justify-center rounded-full bg-secondary-container font-bold text-on-secondary-container transition-all focus:outline-none focus:ring-2 focus:ring-secondary"
        >
            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
        </button>

        <div
            id="mobile-account-menu"
            x-cloak
            x-show="open"
            x-transition
            role="menu"
            class="absolute right-0 top-full mt-2 w-64 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest p-1.5 text-on-surface shadow-lg"
        >
            <div class="border-b border-outline-variant px-3 py-2.5">
                <p class="truncate text-label-md font-label-md text-on-surface">{{ auth()->user()->name ?? 'Administrator' }}</p>
                <p class="mt-0.5 text-body-sm text-on-surface-variant">{{ auth()->user()->roleLabel() }}</p>
            </div>
            <a
                x-ref="profileLink"
                href="{{ route('profile.edit') }}"
                role="menuitem"
                @click="open = false"
                @keydown.arrow-down.prevent="$refs.logoutButton.focus()"
                class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-body-md transition-colors hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none"
            >
                <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">person</span>
                Profil Saya
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" x-ref="logoutButton" role="menuitem" @keydown.arrow-up.prevent="$refs.profileLink.focus()" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-body-md transition-colors hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none">
                    <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">logout</span>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</header>
