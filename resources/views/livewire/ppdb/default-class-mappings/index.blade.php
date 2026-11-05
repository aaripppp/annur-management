<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Pengaturan Kelas Default PPDB</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Tentukan kelas tujuan otomatis untuk setiap tingkat. Pengaturan berlaku untuk semua tahun ajaran.</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8" class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-gutter mb-stack-lg">
        @foreach (['TK' => $levelsByGroup['TK'], 'SD' => $levelsByGroup['SD'], 'SMP' => $levelsByGroup['SMP'], 'SMA' => $levelsByGroup['SMA']] as $groupName => $groupLevels)
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
                <div class="px-stack-md py-3 border-b border-outline-variant flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">class</span>
                    <h2 class="text-title-md font-title-md text-on-surface">{{ $groupName }}</h2>
                </div>
                <div class="divide-y divide-outline-variant/50">
                    @foreach ($groupLevels as $level)
                        @php
                            $options = $classesByLevel->get($level) ?? collect();
                            $selected = $selections[$level] ?? null;
                        @endphp
                        <div class="px-stack-md py-3 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <p class="text-body-md text-on-surface">{{ $levelLabels[$level] ?? $level }}</p>
                                @if ($selected)
                                    <span class="text-label-sm text-secondary">Terkonfigurasi</span>
                                @else
                                    <span class="text-label-sm text-on-surface-variant">Belum diatur</span>
                                @endif
                            </div>
                            <select wire:model="selections.{{ $level }}" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                <option value="">-- Pilih Kelas Default --</option>
                                @forelse ($options as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }} (Level {{ $class->level }})</option>
                                @empty
                                    <option value="" disabled>Tidak ada kelas untuk tingkat ini</option>
                                @endforelse
                            </select>
                            @if ($options->isEmpty())
                                <p class="text-body-xs text-error">Belum ada data SchoolClass untuk tingkat ini. Tambahkan kelas terlebih dahulu.</p>
                            @endif
                            @error('selections.'.$level)
                                <p class="text-body-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <button wire:click="save" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">save</span>
            Simpan Pengaturan
        </button>
    </div>
</div>
