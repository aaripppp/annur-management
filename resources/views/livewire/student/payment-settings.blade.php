<div>
    <!-- Top Subtitle / Breadcrumb -->
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-2">
        <a href="{{ route('siswa.index') }}" class="hover:text-primary transition-colors">Siswa</a>
        <span>&rsaquo;</span>
        <a href="{{ route('siswa.show', $student->id) }}" class="hover:text-primary transition-colors">{{ $student->nama_lengkap }}</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">Pengaturan Pembayaran</span>
    </div>

    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div class="flex items-center gap-4">
            <a href="{{ route('siswa.show', $student->id) }}" class="w-10 h-10 rounded-lg bg-surface-container-low border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 transition-colors" title="Kembali ke detail">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <div>
                <h1 class="text-display-sm font-display-sm text-on-surface">Pengaturan Pembayaran</h1>
                <p class="text-body-md text-on-surface-variant mt-1">
                    {{ $student->nama_lengkap }} &bull; {{ $student->nis ? 'NIS '.$student->nis : 'NIS belum diisi' }} &bull; {{ $student->schoolClass->name ?? 'Kelas tidak diketahui' }}
                </p>
            </div>
        </div>
        <button wire:click="generateBills" class="flex items-center gap-2 bg-primary hover:bg-primary/90 text-on-primary px-4 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm w-fit">
            <span class="material-symbols-outlined text-[18px]">receipt_long</span>
            Generate Tagihan
        </button>
    </div>

    <!-- Toast Success -->
    @if (session()->has('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 3000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-on-secondary-container">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Toast Info -->
    @if (session()->has('info'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-primary-fixed border border-primary text-on-primary-fixed px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-primary">info</span>
            <p class="font-body-md">{{ session('info') }}</p>
        </div>
    @endif

    <!-- Toast Error -->
    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Keikutsertaan Pembayaran -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Keikutsertaan Pembayaran</h2>
            <p class="text-body-sm text-on-surface-variant mt-0.5">Atur jenis pembayaran yang diikuti siswa. Jenis wajib untuk jenjangnya terkunci. Tagihan untuk jenis opsional (mis. Jemputan, Lain-lain) ditambahkan manual dari Buku Tagihan siswa — pengaturan di sini tidak membuat tagihan otomatis.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-3 px-3 w-14 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Jenis Pembayaran</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Status</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Frekuensi</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Mulai Berlaku</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Nominal</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Wajib / Opsional</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($settings as $row)
                        <tr wire:key="payment-setting-{{ $row['payment_type_id'] }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ $loop->iteration }}</td>
                            <td class="py-4 px-6 text-body-md font-body-md text-on-surface">{{ $row['name'] }}</td>
                            <td class="py-4 px-6">
                                @if ($row['is_active'])
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface">
                                        <span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Tidak Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                @if ($row['frequency'])
                                    <span class="text-body-md text-on-surface whitespace-nowrap">{{ $row['frequency'] }}</span>
                                @else
                                    <span class="text-body-sm text-on-surface-variant">&mdash;</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                @if ($row['started_at'])
                                    <span class="text-body-md font-body-md text-on-surface whitespace-nowrap">
                                        {{ $row['started_at']->locale('id')->translatedFormat('F Y') }}
                                    </span>
                                @else
                                    <span class="text-body-sm text-on-surface-variant">&mdash;</span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                @if ($row['default_amount'] !== null)
                                    <div class="text-body-sm text-on-surface-variant">
                                        Default: <span class="font-numeric-data">Rp {{ number_format($row['default_amount'], 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if (! $row['is_required'] && $row['is_active'])
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant">Rp</span>
                                            <input type="number"
                                                   id="custom_amount_{{ $row['payment_type_id'] }}"
                                                   wire:model="customAmountInputs.{{ $row['payment_type_id'] }}"
                                                   min="0"
                                                   step="0.01"
                                                   placeholder="Tarif default"
                                                   class="border border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md pl-9 pr-3 py-2 w-44 font-numeric-data">
                                        </div>
                                        <button type="button"
                                                wire:click="saveCustomAmount({{ $row['payment_type_id'] }})"
                                                class="inline-flex items-center gap-1.5 text-body-md font-body-md text-primary hover:text-primary/80 transition-colors whitespace-nowrap">
                                            <span class="material-symbols-outlined text-[16px]">save</span> Simpan
                                        </button>
                                    </div>
                                    <p class="text-body-sm text-on-surface-variant mt-1">Kosongkan untuk menggunakan tarif default.</p>
                                @elseif ($row['custom_amount'] !== null)
                                    <div class="text-body-sm text-on-surface mt-1">
                                        Khusus: <span class="font-numeric-data">Rp {{ number_format($row['custom_amount'], 0, ',', '.') }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                @if ($row['is_required'])
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container">
                                        <span class="material-symbols-outlined text-[14px]">lock</span> Wajib
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface">
                                        Opsional
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-right">
                                @if ($row['is_required'])
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-low text-on-surface-variant" title="Tidak bisa dinonaktifkan">
                                        <span class="material-symbols-outlined text-[14px]">lock</span> LOCKED
                                    </span>
                                @else
                                    <div class="flex items-center justify-end gap-3">
                                        @if ($row['is_active'])
                                            <button type="button"
                                                    wire:click="editStartDate({{ $row['payment_type_id'] }})"
                                                    title="Ubah mulai berlaku"
                                                    class="flex items-center gap-1.5 text-body-md font-body-md text-primary hover:text-primary/80 transition-colors whitespace-nowrap">
                                                <span class="material-symbols-outlined text-[16px]">edit_calendar</span> Ubah
                                            </button>
                                            <button type="button"
                                                    wire:click="toggle({{ $row['payment_type_id'] }})"
                                                    title="Nonaktifkan"
                                                    class="relative w-11 h-6 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 bg-primary">
                                                <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform translate-x-5"></span>
                                            </button>
                                        @else
                                            <button type="button"
                                                    wire:click="confirmActivate({{ $row['payment_type_id'] }})"
                                                    class="flex items-center gap-2 bg-primary hover:bg-primary/90 text-on-primary px-4 py-2 rounded-lg font-label-lg transition-colors shadow-sm whitespace-nowrap">
                                                <span class="material-symbols-outlined text-[18px]">add</span>
                                                Aktifkan
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">toggle_off</span>
                                <p>Tidak ada jenis pembayaran ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Mulai Berlaku -->
    @if ($isActivateOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" wire:click="closeActivate"></div>
            <div class="relative bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xl w-full max-w-md p-6 m-4">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-headline-sm font-headline-sm text-on-surface">
                            {{ $isEditingStartDate ? 'Ubah Mulai Berlaku' : 'Aktifkan '.$activateTypeName }}
                        </h3>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">
                            Tagihan opsional ditambahkan manual dari Buku Tagihan siswa. Pengaturan ini hanya mencatat keikutsertaan; tidak membuat tagihan otomatis.
                        </p>
                    </div>
                    <button type="button" wire:click="closeActivate"
                            class="shrink-0 w-8 h-8 rounded-lg bg-surface-container-low border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-on-surface transition-colors"
                            title="Tutup">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
                <label for="activate_start_month" class="block text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider mb-1.5">
                    Mulai Berlaku
                </label>
                <input type="month" id="activate_start_month" wire:model="activateStartMonth"
                       class="w-full border border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md px-3 py-2.5">
                @error('activateStartMonth')
                    <p class="text-body-sm text-error mt-1.5">{{ $message }}</p>
                @enderror
                <div class="flex items-center justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeActivate"
                            class="flex items-center gap-2 border border-outline-variant text-on-surface px-4 py-2.5 rounded-lg font-label-lg hover:bg-surface-container-low transition-colors">
                        Batal
                    </button>
                    <button type="button" wire:click="activate"
                            class="flex items-center gap-2 bg-primary hover:bg-primary/90 text-on-primary px-4 py-2.5 rounded-lg font-label-lg transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">check</span>
                        Aktifkan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
