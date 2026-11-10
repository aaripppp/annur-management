@extends('layouts.app')

@section('content')
    <section class="w-full max-w-lg self-center rounded-2xl border border-outline-variant bg-surface-container-lowest p-8 text-center shadow-sm">
        <img src="{{ asset('images/annur_logo2.png') }}" alt="Annur" class="mx-auto mb-5 h-16 w-auto">
        <h1 class="text-headline-md font-headline-md text-on-surface">Akses TU Unit sedang disiapkan</h1>
        <p class="mt-3 text-body-md text-on-surface-variant">
            Akun {{ auth()->user()->school_level?->value ?? 'TU' }} Anda sudah aktif. Akses Data Siswa dan Calon Siswa akan tersedia setelah pembatasan unit selesai diterapkan.
        </p>
    </section>
@endsection
