<div>
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Jenis Pembayaran</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola jenis-jenis pembayaran yang tersedia di sekolah.</p>
        </div>
        <button wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Jenis
        </button>
    </div>

    <!-- Summary Cards -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Jenis Pembayaran</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 tracking-wider">{{ number_format($totalTypes, 0, ',', '.') }} Jenis</p>
            </div>
        </div>
    </section>

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
            <span class="material-symbols-outlined text-secondary">check_circle</span>
             <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Toast Error -->
    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
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

    <!-- Table -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant flex justify-between items-center">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Jenis Pembayaran</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-3 px-3 w-14 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Nama</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Frekuensi</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Jenjang</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($paymentTypes as $type)
                        <tr wire:key="payment-type-{{ $type->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ ($paymentTypes->currentPage() - 1) * $paymentTypes->perPage() + $loop->iteration }}</td>
                            <td class="py-4 px-6 text-body-md font-body-md text-on-surface">
                                {{ $type->name }}
                                @if($type->audience?->value === 'prospective_student')
                                    <span class="inline-flex ml-2 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed text-label-sm font-label-sm">Calon Siswa</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($type->is_active)
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface">
                                        <span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($type->rates->pluck('billing_frequency')->filter()->unique(fn($frequency) => $frequency->value) as $frequency)
                                        <span class="inline-flex px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface text-label-sm font-label-sm">
                                            {{ match($frequency->value) { 'yearly' => 'Tahunan', 'one_time' => 'Sekali Bayar', default => 'Bulanan' } }}
                                        </span>
                                    @empty
                                        <span class="text-body-sm text-on-surface-variant">Belum ada tarif</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($type->paymentTypeSchoolLevels->where('is_active', true)->sortBy(fn($mapping) => array_search($mapping->school_level->value, ['TK', 'SD', 'SMP', 'SMA'], true)) as $mapping)
                                        <span class="inline-flex px-2 py-0.5 rounded-full bg-primary-container text-on-primary-container text-label-sm font-label-sm">{{ $mapping->school_level->value }}</span>
                                    @empty
                                        <span class="text-body-sm text-on-surface-variant">Manual saja</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $type->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button wire:click="confirmDelete({{ $type->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">receipt_long</span>
                                <p>Tidak ada data jenis pembayaran ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-outline-variant">
            {{ $paymentTypes->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    <!-- Modal Form -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">
                        {{ $isEditing ? 'Edit Jenis Pembayaran' : 'Tambah Jenis Baru' }}
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="save" class="flex flex-col gap-5">
                        <div>
                            <label for="name" class="block text-label-md font-label-md text-on-surface mb-1">Nama Jenis Pembayaran <span class="text-error">*</span></label>
                            <input type="text" id="name" wire:model="name" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: SPP Bulanan">
                            @error('name') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-2">Audience</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 border border-outline-variant rounded-lg px-3 py-2.5 cursor-pointer hover:bg-surface-container-low transition-colors">
                                    <input type="radio" wire:model="audience" value="student" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Siswa</span>
                                </label>
                                <label class="flex items-center gap-2 border border-outline-variant rounded-lg px-3 py-2.5 cursor-pointer hover:bg-surface-container-low transition-colors">
                                    <input type="radio" wire:model="audience" value="prospective_student" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Calon Siswa</span>
                                </label>
                            </div>
                            <p class="text-body-sm text-on-surface-variant mt-2">Calon siswa dipakai untuk tagihan pendaftaran; tidak dihasilkan untuk billing siswa.</p>
                            @error('audience') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                        <fieldset>
                            <legend class="block text-label-md font-label-md text-on-surface mb-2">Berlaku untuk</legend>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($schoolLevelOptions as $schoolLevel)
                                    <label class="flex items-center gap-2 border border-outline-variant rounded-lg px-3 py-2.5 cursor-pointer hover:bg-surface-container-low transition-colors">
                                        <input type="checkbox" wire:model="schoolLevels" value="{{ $schoolLevel->value }}" class="rounded text-primary focus:ring-primary border-outline-variant">
                                        <span class="text-body-md text-on-surface">{{ $schoolLevel->value }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-body-sm text-on-surface-variant mt-2">Jenjang aktif akan mengikuti billing otomatis jika tarif efektif tersedia.</p>
                            @error('schoolLevels.*') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </fieldset>
                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-2">Status</label>
                            <div class="flex gap-4 items-center">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="is_active" wire:model="is_active" value="1" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Aktif</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="is_active" wire:model="is_active" value="0" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Nonaktif</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="save" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete_forever</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Jenis Pembayaran?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Tindakan ini tidak bisa dibatalkan.<br>Data jenis pembayaran akan dihapus secara permanen.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDelete" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="delete" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
