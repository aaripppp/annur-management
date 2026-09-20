<!-- Sidebar header -->
<div class="flex items-center gap-3 mb-5 px-2">
    <img src="{{ asset('images/annur_logo2.png') }}" alt="Annur" class="h-11 w-auto max-w-12 object-contain shrink-0">
    <div>
        <h1 class="text-headline-md font-headline-md text-on-primary">Annur Management</h1>
        <p class="text-body-sm font-body-sm text-on-primary-container">Sistem Manajemen SPP</p>
    </div>
</div>

<!-- Main Nav -->
<nav class="flex-1 flex flex-col gap-1 overflow-y-auto" x-data="sidebarScrollPosition()" @scroll.passive="onScroll()">
    <a class="{{ request()->routeIs('dashboard') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200" href="{{ route('dashboard') }}">
        <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
        Dashboard
    </a>

    <!-- Siswa -->
    <div class="flex flex-col gap-1" x-data="sidebarMenu('siswa', @json($isStudentActive))">
        <button
            type="button"
            id="nav-student-toggle"
            aria-controls="nav-student-panel"
            :aria-expanded="siswaOpen ? 'true' : 'false'"
            @click="toggle()"
            class="w-full text-left {{ $isStudentActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center justify-between gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200"
        >
            <span class="flex items-center gap-3">
                <span class="material-symbols-outlined" data-icon="group">group</span>
                <span>Siswa</span>
            </span>
            <span class="material-symbols-outlined text-[20px] transition-transform duration-200" :class="siswaOpen ? 'rotate-180' : ''" data-icon="expand_more">expand_more</span>
        </button>
        <div id="nav-student-panel" role="region" aria-labelledby="nav-student-toggle" class="ml-4 pl-3 border-l-2 border-on-primary-fixed-variant/50 flex flex-col gap-1" x-show="siswaOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <a class="{{ $isStudentDataActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('siswa.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="person">person</span>
                Data Siswa
            </a>
            <a class="{{ $isCalonSiswaActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('calon-siswa.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="person_add">person_add</span>
                Calon Siswa
            </a>
            <a class="{{ $isStudentExamActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('siswa.exam-eligibility') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="fact_check">fact_check</span>
                Kelayakan Ujian
            </a>
            <a class="{{ $isStudentPaymentActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('pembayaran.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="payments">payments</span>
                Pembayaran
            </a>
            <a class="{{ $isStudentReportActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('laporan.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="assessment">assessment</span>
                Laporan
            </a>
        </div>
    </div>

    <!-- Daycare -->
    <div class="flex flex-col gap-1" x-data="sidebarMenu('daycare', @json($isDaycareActive))">
        <button
            type="button"
            id="nav-daycare-toggle"
            aria-controls="nav-daycare-panel"
            :aria-expanded="daycareOpen ? 'true' : 'false'"
            @click="toggle()"
            class="w-full text-left {{ $isDaycareActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center justify-between gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200"
        >
            <span class="flex items-center gap-3">
                <span class="material-symbols-outlined" data-icon="child_care">child_care</span>
                <span>Daycare</span>
            </span>
            <span class="material-symbols-outlined text-[20px] transition-transform duration-200" :class="daycareOpen ? 'rotate-180' : ''" data-icon="expand_more">expand_more</span>
        </button>
        <div id="nav-daycare-panel" role="region" aria-labelledby="nav-daycare-toggle" class="ml-4 pl-3 border-l-2 border-on-primary-fixed-variant/50 flex flex-col gap-1" x-show="daycareOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <a class="{{ $isDaycareDataActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('daycare.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="group">group</span>
                Data Daycare
            </a>
            <a class="{{ $isDaycarePaymentActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('daycare.payment.entry') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="receipt_long">receipt_long</span>
                Pembayaran
            </a>
            <a class="{{ $isDaycareReportActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('daycare.report.daily') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="description">description</span>
                Laporan
            </a>
        </div>
    </div>

    <!-- Master Data -->
    <div class="flex flex-col gap-1" x-data="sidebarMenu('masterData', @json($isMasterDataActive))">
        <button
            type="button"
            id="nav-master-data-toggle"
            aria-controls="nav-master-data-panel"
            :aria-expanded="masterDataOpen ? 'true' : 'false'"
            @click="toggle()"
            class="w-full text-left {{ $isMasterDataActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center justify-between gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200"
        >
            <span class="flex items-center gap-3">
                <span class="material-symbols-outlined" data-icon="database">database</span>
                <span>Master Data</span>
            </span>
            <span class="material-symbols-outlined text-[20px] transition-transform duration-200" :class="masterDataOpen ? 'rotate-180' : ''" data-icon="expand_more">expand_more</span>
        </button>
        <div id="nav-master-data-panel" role="region" aria-labelledby="nav-master-data-toggle" class="ml-4 pl-3 border-l-2 border-on-primary-fixed-variant/50 flex flex-col gap-1" x-show="masterDataOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <a class="{{ request()->routeIs('bank.*') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('bank.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="account_balance">account_balance</span>
                Bank
            </a>
            <a class="{{ request()->routeIs('jenis-pembayaran.*') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('jenis-pembayaran.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="tune">tune</span>
                Jenis Bayar
            </a>
            <a class="{{ request()->routeIs('tarif-pembayaran.*') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('tarif-pembayaran.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="request_quote">request_quote</span>
                Tarif SPP
            </a>
            <a class="{{ request()->routeIs('kelas.*') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('kelas.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="class">class</span>
                Kelas
            </a>
            <a class="{{ request()->routeIs('tahun-ajaran.*') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('tahun-ajaran.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="calendar_today">calendar_today</span>
                Tahun Ajaran
            </a>
            <a class="{{ request()->routeIs('aturan-kenaikan-kelas.*') ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-1.5 transition-colors duration-200" href="{{ route('aturan-kenaikan-kelas.index') }}">
                <span class="material-symbols-outlined text-[20px]" data-icon="rule">rule</span>
                Aturan Kelas
            </a>
        </div>
    </div>
</nav>

<!-- Footer Nav -->
<div class="mt-auto pt-3 border-t border-on-primary-fixed-variant flex flex-col gap-1">
    <a class="text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary rounded-lg flex items-center gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200" href="#">
        <span class="material-symbols-outlined" data-icon="settings">settings</span>
        Pengaturan
    </a>
    @if (auth()->user()->isSuperAdmin())
        <a class="{{ $isAccountActive ? 'bg-secondary-container text-on-secondary-container font-bold' : 'text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary' }} rounded-lg flex items-center gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200" href="{{ route('akun.index') }}">
            <span class="material-symbols-outlined" data-icon="manage_accounts">manage_accounts</span>
            Manajemen Akun
        </a>
    @endif
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full text-on-primary-container hover:bg-on-primary-fixed-variant hover:text-on-primary rounded-lg flex items-center gap-3 px-3 py-2 scale-95 active:scale-90 transition-transform transition-colors duration-200 cursor-pointer">
            <span class="material-symbols-outlined" data-icon="logout">logout</span>
            Keluar
        </button>
    </form>
</div>