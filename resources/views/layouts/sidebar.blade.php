<!-- SideNavBar -->
@php
    $isStudentActive = request()->routeIs('siswa.*')
        || request()->routeIs('calon-siswa.*')
        || request()->routeIs('pembayaran.*')
        || request()->routeIs('laporan.*');

    $isStudentDataActive = request()->routeIs('siswa.index', 'siswa.import', 'siswa.show', 'siswa.payment-settings');
    $isStudentExamActive = request()->routeIs('siswa.exam-eligibility');
    $isCalonSiswaActive = request()->routeIs('calon-siswa.*');
    $isStudentPaymentActive = request()->routeIs('pembayaran.*');
    $isStudentReportActive = request()->routeIs('laporan.*');

    $isDaycareActive = request()->routeIs('daycare.*');

    $isDaycareDataActive = request()->routeIs('daycare.show') || request()->routeIs('daycare.index');
    $isDaycarePaymentActive = request()->routeIs('daycare.payment.*');
    $isDaycareReportActive = request()->routeIs('daycare.report.*');

    $isMasterDataActive = request()->routeIs('bank.*')
        || request()->routeIs('jenis-pembayaran.*')
        || request()->routeIs('tarif-pembayaran.*')
        || request()->routeIs('kelas.*')
        || request()->routeIs('tahun-ajaran.*')
        || request()->routeIs('aturan-kenaikan-kelas.*');

    $isAccountActive = request()->routeIs('akun.*');
@endphp

<!-- Drawer overlay (mobile / <lg) -->
<div
    x-show="sidebarOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="close()"
    style="display: none"
    class="fixed inset-0 z-40 bg-black/40 lg:hidden"
    aria-hidden="true"
></div>

<!-- Sidebar: static drawer on desktop (≥lg), off-canvas drawer on mobile (<lg) -->
<aside
    id="main-sidebar"
    class="sidebar-drawer bg-primary dark:bg-primary text-on-primary dark:text-on-primary font-body-md text-body-md fixed left-0 top-0 h-screen w-sidebar-width border-r border-outline-variant dark:border-outline flex flex-col p-stack-md z-50"
    :class="sidebarOpen ? 'sidebar-drawer-open' : ''"
    aria-label="Menu navigasi"
>
    @include('layouts._sidebar-nav')
</aside>