<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Pengaturan Tahun Ajaran PPDB</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Tentukan tahun ajaran yang dibuka untuk pendaftaran online. Pengaturan ini tidak mengubah tahun ajaran aktif sekolah.</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->has('openYears') || $errors->has('openYears.*'))
        <div class="mb-stack-md bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl flex items-center gap-3">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ $errors->first('openYears') ?: $errors->first('openYears.*') }}</p>
        </div>
    @endif

    <form wire:submit="save" class="space-y-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
            @if ($academicYears->isEmpty())
                <div class="px-stack-lg py-12 text-center">
                    <span class="material-symbols-outlined text-on-surface-variant text-5xl">calendar_month</span>
                    <h2 class="text-title-md font-title-md text-on-surface mt-3">Belum ada tahun ajaran</h2>
                    <p class="text-body-md text-on-surface-variant mt-1">Tambahkan tahun ajaran terlebih dahulu sebelum membuka pendaftaran online.</p>
                </div>
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-surface-container-low border-b border-outline-variant">
                            <tr>
                                <th class="px-stack-md py-3 text-label-lg text-on-surface">Tahun Ajaran</th>
                                <th class="px-stack-md py-3 text-label-lg text-on-surface">Status Sekolah</th>
                                <th class="px-stack-md py-3 text-label-lg text-on-surface">Status PPDB</th>
                                <th class="px-stack-md py-3 text-label-lg text-on-surface text-right">Buka Pendaftaran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/50">
                            @foreach ($academicYears as $academicYear)
                                @php($isOpen = (bool) ($openYears[$academicYear->id] ?? false))
                                <tr wire:key="ppdb-year-desktop-{{ $academicYear->id }}">
                                    <td class="px-stack-md py-4">
                                        <p class="text-body-lg font-semibold text-on-surface">{{ $academicYear->year }}</p>
                                        <p class="text-body-sm text-on-surface-variant">{{ $academicYear->start_date->format('d M Y') }} - {{ $academicYear->end_date?->format('d M Y') ?? '-' }}</p>
                                    </td>
                                    <td class="px-stack-md py-4">
                                        @if ($academicYear->is_active)
                                            <span class="inline-flex items-center rounded-full bg-primary-container px-3 py-1 text-label-sm text-on-primary-container">Aktif</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-surface-container-high px-3 py-1 text-label-sm text-on-surface-variant">Tidak Aktif</span>
                                        @endif
                                    </td>
                                    <td class="px-stack-md py-4">
                                        @if ($isOpen)
                                            <span class="inline-flex items-center rounded-full bg-secondary-container px-3 py-1 text-label-sm text-on-secondary-container">Dibuka</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-surface-container-high px-3 py-1 text-label-sm text-on-surface-variant">Ditutup</span>
                                        @endif
                                    </td>
                                    <td class="px-stack-md py-4 text-right">
                                        <label for="ppdb-year-desktop-toggle-{{ $academicYear->id }}" class="inline-flex items-center cursor-pointer">
                                            <input id="ppdb-year-desktop-toggle-{{ $academicYear->id }}" type="checkbox" wire:model.live="openYears.{{ $academicYear->id }}" class="sr-only peer">
                                            <span class="relative h-6 w-11 rounded-full bg-surface-container-highest peer-checked:bg-primary transition-colors after:content-[''] after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
                                            <span class="sr-only">Buka PPDB tahun ajaran {{ $academicYear->year }}</span>
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="md:hidden divide-y divide-outline-variant/50">
                    @foreach ($academicYears as $academicYear)
                        @php($isOpen = (bool) ($openYears[$academicYear->id] ?? false))
                        <div class="p-stack-md space-y-3" wire:key="ppdb-year-mobile-{{ $academicYear->id }}">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-title-md font-title-md text-on-surface">{{ $academicYear->year }}</p>
                                    <p class="text-body-sm text-on-surface-variant mt-1">{{ $academicYear->start_date->format('d M Y') }} - {{ $academicYear->end_date?->format('d M Y') ?? '-' }}</p>
                                </div>
                                <label for="ppdb-year-mobile-toggle-{{ $academicYear->id }}" class="inline-flex items-center cursor-pointer shrink-0">
                                    <input id="ppdb-year-mobile-toggle-{{ $academicYear->id }}" type="checkbox" wire:model.live="openYears.{{ $academicYear->id }}" class="sr-only peer">
                                    <span class="relative h-6 w-11 rounded-full bg-surface-container-highest peer-checked:bg-primary transition-colors after:content-[''] after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-transform peer-checked:after:translate-x-5"></span>
                                    <span class="sr-only">Buka PPDB tahun ajaran {{ $academicYear->year }}</span>
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full {{ $academicYear->is_active ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }} px-3 py-1 text-label-sm">
                                    Sekolah: {{ $academicYear->is_active ? 'Aktif' : 'Tidak Aktif' }}
                                </span>
                                <span class="inline-flex items-center rounded-full {{ $isOpen ? 'bg-secondary-container text-on-secondary-container' : 'bg-surface-container-high text-on-surface-variant' }} px-3 py-1 text-label-sm">
                                    PPDB: {{ $isOpen ? 'Dibuka' : 'Ditutup' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($academicYears->isEmpty()) class="bg-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2">
                <span wire:loading.remove wire:target="save" class="material-symbols-outlined text-[20px]">save</span>
                <span wire:loading wire:target="save" class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span>
                <span wire:loading.remove wire:target="save">Simpan Pengaturan</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </button>
        </div>
    </form>
</div>
