<div>
    <!-- Top Subtitle / Breadcrumb -->
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-2">
        <a href="{{ route('siswa.index') }}" class="hover:text-primary transition-colors">Siswa</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">{{ $student->nama_lengkap }}</span>
    </div>

    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div class="flex items-center gap-4">
            <a href="{{ route('siswa.index') }}" class="w-10 h-10 rounded-lg bg-surface-container-low border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 transition-colors" title="Kembali">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            @if($student->foto)
                <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($student->foto) }}" alt="Foto {{ $student->nama_lengkap }}" class="w-16 h-16 rounded-2xl object-cover border border-outline-variant shrink-0">
            @else
                <div class="w-16 h-16 rounded-2xl bg-surface-container-high border border-outline-variant text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true">
                    <span class="material-symbols-outlined text-[30px]">person</span>
                </div>
            @endif
            <div>
                <h1 class="text-display-sm font-display-sm text-on-surface">{{ $student->nama_lengkap }}</h1>
                <p class="text-body-md text-on-surface-variant mt-1">
                    {{ $student->nis ? 'NIS '.$student->nis : 'NIS belum diisi' }}
                    @php
                        $academicStatus = $student->academicStatus();
                    @endphp
                    @if($academicStatus === 'lulus')
                        <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-on-tertiary-fixed">
                            <span class="material-symbols-outlined text-[14px]">school</span>
                            Lulus
                        </span>
                    @elseif($academicStatus === 'calon_siswa')
                        <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                            <span class="material-symbols-outlined text-[14px]">person_add</span>
                            Calon Siswa
                        </span>
                    @elseif($academicStatus === 'aktif')
                        <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-secondary-fixed text-on-secondary-fixed">
                            <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                            Aktif
                        </span>
                    @endif
                </p>
                @if($academicStatus === 'calon_siswa')
                    @php
                        $entryYear = $student->entryYearLabel();
                    @endphp
                    <p class="text-body-sm text-on-surface-variant mt-1">
                        Rencana Masuk : {{ $entryYear ?? '-' }} &bull;
                        Kelas Masuk : {{ $student->academicClassLabel() }}
                    </p>
                @elseif($academicStatus === 'lulus')
                    <p class="text-body-sm text-on-surface-variant mt-1">
                        Kelas Terakhir : {{ $student->academicClassLabel() }}
                    </p>
                @else
                    <p class="text-body-sm text-on-surface-variant mt-1">
                        Kelas : {{ $student->academicClassLabel() }}
                    </p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('siswa.bills.pdf', ['student' => $student->id, 'academic_year' => $selectedAcademicYear]) }}" target="_blank" class="flex items-center gap-1.5 text-primary font-label-md border border-primary rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors" title="Cetak daftar tagihan siswa ke PDF.">
                <span class="material-symbols-outlined text-[18px]">print</span>
                Cetak Tagihan
            </a>
            <button wire:click="openBillbook" class="flex items-center gap-1.5 text-on-surface-variant font-label-md border border-outline-variant rounded-xl px-3.5 py-2 hover:bg-surface-container transition-colors" title="Lengkapi tagihan yang belum ada untuk siswa lama.">
                <span class="material-symbols-outlined text-[18px]">menu_book</span>
                Lengkapi Tagihan
            </button>
        </div>
    </div>

    <!-- Toast Success -->
    @if (session()->has('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
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

    <!-- Kartu riwayat konversi calon siswa -->
    <x-student.registration-history-card :prospect="$registeredProspect" :summary="$registrationSummary" />

    <!-- Filter Periode Summary -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-stack-lg">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <label for="academic_year" class="text-body-md font-body-md text-on-surface-variant">Tahun Ajaran:</label>
                <select id="academic_year" wire:model.live="selectedAcademicYear" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[160px]">
                    <option value="">Semua</option>
                    @foreach($academicYearSelectorOptions as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="summary_category" class="text-body-md font-body-md text-on-surface-variant">Ringkasan:</label>
                <select id="summary_category" wire:model.live="summaryCategory" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[200px]">
                    <option value="all">Semua Tagihan</option>
                    <option value="monthly">Tagihan Bulanan</option>
                    <option value="yearly">Tagihan Tahunan</option>
                    <option value="one_time">Tagihan Sekali Bayar</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Ringkasan Cards -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Tagihan</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-tertiary-fixed flex items-center justify-center text-on-tertiary-fixed">
                    <span class="material-symbols-outlined">payments</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Dibayar</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($totalDibayar, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-error-container flex items-center justify-center text-on-error-container">
                    <span class="material-symbols-outlined">priority_high</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Tunggakan</p>
                <p class="text-headline-md font-headline-md text-error mt-1 font-numeric-data tracking-wider">Rp {{ number_format($totalTunggakan, 0, ',', '.') }}</p>
            </div>
        </div>
    </section>

    <!-- Tagihan Aktif (dikelompokkan per bulan tagihan, per tahun ajaran, dan lainnya) -->
    @if ($groupedMonthlyBills->isEmpty() && $groupedYearlyBills->isEmpty() && $oneTimeBills->isEmpty())
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mb-stack-lg">
            <div class="px-5 py-4 border-b border-outline-variant">
                <h2 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Aktif</h2>
                <p class="text-body-sm text-on-surface-variant mt-0.5">Semua tagihan siswa, termasuk tagihan manual, ditampilkan di buku tagihan.</p>
            </div>
            <div class="p-8 text-center text-on-surface-variant">
                <span class="material-symbols-outlined text-4xl mb-2 block">receipt_long</span>
                <p>Belum ada tagihan untuk siswa ini.</p>
                <p class="text-body-sm mt-1">Buku tagihan dibuat otomatis saat siswa ditambahkan. Gunakan "Lengkapi Tagihan" untuk membuat tagihan bagi siswa lama.</p>
            </div>
        </div>
    @else
        @foreach ($groupedMonthlyBills as $group)
            <div wire:key="month-group-{{ $group['period_year'] }}-{{ $group['period_month'] }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mb-stack-lg">
            <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row justify-between items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-headline-sm font-headline-sm text-on-surface">{{ $group['title'] }}</h2>
                        @include('livewire.student.period-status-badge', ['status' => $group['status']])
                    </div>
                    <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $group['count'] }} tagihan · Total sisa Rp {{ number_format($group['total_remaining'], 0, ',', '.') }}</p>
                </div>
                <button wire:click="openAddBillForMonth({{ $group['period_month'] }}, {{ $group['period_year'] }})" class="flex items-center gap-1.5 text-primary font-label-lg border border-primary/40 rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add_box</span>
                    Tambah Tagihan
                </button>
            </div>

                @include('livewire.student.bill-table', ['bills' => $group['bills']])
            </div>
        @endforeach

        @if ($groupedMonthlyBills->isEmpty() && $groupedYearlyBills->isNotEmpty())
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mb-stack-lg">
                <div class="p-5 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-3xl mb-2 block">calendar_month</span>
                    <p class="text-body-md">Tagihan bulanan belum digenerate untuk tahun ajaran ini.</p>
                    <p class="text-body-sm mt-1">Gunakan menu "Generate Tagihan Bulanan" di halaman Tahun Ajaran untuk membuat tagihan bulanan bagi siswa aktif.</p>
                </div>
            </div>
        @endif

        @foreach ($groupedYearlyBills as $group)
            <div wire:key="year-group-{{ $group['academic_year'] }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mb-stack-lg">
                <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row justify-between items-center gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Tahunan</h2>
                            @include('livewire.student.period-status-badge', ['status' => $group['status']])
                        </div>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">Tahun Ajaran {{ $group['academic_year'] }} · {{ $group['count'] }} tagihan · Total sisa Rp {{ number_format($group['total_remaining'], 0, ',', '.') }}</p>
                    </div>
                    <button wire:click="openAddBillForAcademicYear('{{ $group['academic_year'] }}')" class="flex items-center gap-1.5 text-primary font-label-lg border border-primary/40 rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">add_box</span>
                        Tambah Tagihan
                    </button>
                </div>

                @include('livewire.student.bill-table', ['bills' => $group['bills']])
            </div>
        @endforeach

        @if ($oneTimeBills->isNotEmpty())
            <div wire:key="one-time-bills-section" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mb-stack-lg">
                <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row justify-between items-center gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Sekali Bayar</h2>
                            @include('livewire.student.period-status-badge', ['status' => $oneTimeStatus])
                        </div>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $oneTimeBills->count() }} tagihan · Total sisa Rp {{ number_format($oneTimeBills->sum(fn ($bill) => (float) $bill->remaining_amount), 0, ',', '.') }}</p>
                    </div>
                    <button wire:click="openAddBillForOneTime" class="flex items-center gap-1.5 text-primary font-label-lg border border-primary/40 rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">add_box</span>
                        Tambah Tagihan
                    </button>
                </div>

                @include('livewire.student.bill-table', ['bills' => $oneTimeBills])
            </div>
        @endif
    @endif

    <!-- Modal Generate Tagihan Sampai -->
    @if($isGenerateUntilOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Generate Tagihan Sampai</h3>
                    <button wire:click="closeGenerateUntil" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="generateUntilBills" class="flex flex-col gap-5">
                        <div>
                            <label for="generate_until_month" class="block text-label-md font-label-md text-on-surface mb-1">Bulan Tujuan <span class="text-error">*</span></label>
                            <input type="month" id="generate_until_month" wire:model="generateUntilMonth" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            @error('generateUntilMonth') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                        <p class="text-body-sm text-on-surface-variant flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">info</span>
                            Tagihan dibuat untuk setiap bulan dari periode berjalan sampai bulan tujuan. Tagihan yang sudah ada akan dipakai ulang, tidak dibuat duplikat.
                        </p>
                    </form>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeGenerateUntil" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="generateUntilBills" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Generate</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Generate Buku Tagihan -->
    @if($isBillbookOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Generate Buku Tagihan</h3>
                    <button wire:click="closeBillbook" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="generateBillbook" class="flex flex-col gap-5">
                        <div>
                            <label for="billbook_start_month" class="block text-label-md font-label-md text-on-surface mb-1">Bulan Awal <span class="text-error">*</span></label>
                            <input type="month" id="billbook_start_month" wire:model="billbookStartMonth" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            @error('billbookStartMonth') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                        <p class="text-body-sm text-on-surface-variant flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">info</span>
                            Buku tagihan dibuat otomatis saat siswa baru ditambahkan. Tombol ini untuk melengkapi tagihan yang hilang atau backfill siswa lama. Buku dibuat dari bulan awal sampai Juni; tagihan yang sudah ada dipakai ulang tanpa duplikat, dan nominal yang sudah diedit tidak akan ditimpa.
                        </p>
                    </form>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeBillbook" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="generateBillbook" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Generate</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Edit Tagihan -->
    @if($isEditOpen && $editBillId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Edit Tagihan</h3>
                    <button wire:click="closeEdit" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex flex-col gap-6">
                    <div>
                        <p class="text-body-md text-on-surface font-medium">{{ $editTypeName }} <span class="text-on-surface-variant">- {{ $editPeriodLabel }}</span></p>
                    </div>

                    <div>
                        <label for="edit_amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal Tagihan <span class="text-error">*</span></label>
                        <div class="relative"
                             x-data="{
                                 display: '{{ $editAmount ? number_format((int) $editAmount, 0, ',', '.') : '' }}',
                                 handleInput(e) {
                                     const raw = e.target.value.replace(/[^0-9]/g, '');
                                     const num = parseInt(raw) || 0;
                                     this.display = num ? num.toLocaleString('id-ID') : '';
                                     clearTimeout(this._t);
                                     this._t = setTimeout(() => $wire.set('editAmount', num), 300);
                                 }
                             }">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                            <input type="text"
                                   id="edit_amount"
                                   x-model="display"
                                   @input="handleInput($event)"
                                   inputmode="numeric"
                                   placeholder="0"
                                   class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                        </div>
                        @error('editAmount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="bg-surface-container-low border border-outline-variant rounded-xl divide-y divide-outline-variant">
                        <div class="flex justify-between px-4 py-2.5 text-body-md">
                            <span class="text-on-surface-variant">Sudah Dibayar</span>
                            <span class="text-tertiary font-semibold">Rp {{ number_format($editPaidAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between px-4 py-2.5 text-body-md">
                            <span class="text-on-surface-variant">Sisa</span>
                            <span class="{{ $editPaidAmount > 0 ? 'text-error' : 'text-on-surface-variant' }} font-semibold">Rp {{ number_format(max(0, (float) $editAmount - $editPaidAmount), 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeEdit" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="saveEditBill" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Tambah Tagihan Manual -->
    @if($isAddOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Tambah Tagihan Manual</h3>
                    <button wire:click="closeAddBill" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex flex-col gap-4">
                    <div>
                        <label for="add_payment_type_id" class="block text-label-md font-label-md text-on-surface mb-1">Jenis Pembayaran <span class="text-error">*</span></label>
                        <select id="add_payment_type_id" wire:model.live="addPaymentTypeId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <option value="">Pilih jenis pembayaran</option>
                            @foreach ($manualAddPaymentTypes as $ptype)
                                <option value="{{ $ptype->id }}">{{ $ptype->name }}</option>
                            @endforeach
                        </select>
                        @if ($manualAddPaymentTypes->isEmpty())
                            <p class="text-body-sm text-on-surface-variant mt-1.5">Tidak ada jenis pembayaran yang tersedia.</p>
                        @endif
                        @error('addPaymentTypeId') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="add_amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal Tagihan <span class="text-error">*</span></label>
                        <div class="relative"
                             x-data="{
                                 display: '{{ $addAmount ? number_format((int) $addAmount, 0, ',', '.') : '' }}',
                                 handleInput(e) {
                                     const raw = e.target.value.replace(/[^0-9]/g, '');
                                     const num = parseInt(raw) || 0;
                                     this.display = num ? num.toLocaleString('id-ID') : '';
                                     clearTimeout(this._t);
                                     this._t = setTimeout(() => $wire.set('addAmount', num), 300);
                                 }
                             }">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                            <input type="text"
                                   id="add_amount"
                                   x-model="display"
                                   @input="handleInput($event)"
                                   inputmode="numeric"
                                   placeholder="0"
                                   class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                        </div>
                        @error('addAmount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>

                    @if ($addFrequency === 'monthly')
                        @if ($addPeriodLocked && $addLockedMonth && $addLockedYear)
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-body-sm text-on-surface-variant">Periode</span>
                                    <span class="text-body-md text-on-surface font-semibold font-numeric-data">
                                        {{ Carbon\Carbon::createFromDate($addLockedYear, $addLockedMonth, 1)->locale('id')->translatedFormat('F Y') }}
                                    </span>
                                </div>
                                <p class="text-body-sm text-on-surface-variant mt-1 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px]">lock</span>
                                    Periode diambil otomatis dari bagian tagihan yang dipilih.
                                </p>
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="add_month" class="block text-label-md font-label-md text-on-surface mb-1">Bulan <span class="text-error">*</span></label>
                                    <select id="add_month" wire:model="addMonth" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                        @foreach ($addMonthOptions as $option)
                                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('addMonth') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="add_year" class="block text-label-md font-label-md text-on-surface mb-1">Tahun <span class="text-error">*</span></label>
                                    <select id="add_year" wire:model="addYear" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                        @foreach ($addYearOptions as $yearOption)
                                            <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                                        @endforeach
                                    </select>
                                    @error('addYear') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif
                    @elseif ($addFrequency === 'yearly')
                        @if ($addPeriodLocked && $addLockedAcademicYear)
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-body-sm text-on-surface-variant">Tahun Ajaran</span>
                                    <span class="text-body-md text-on-surface font-semibold font-numeric-data">{{ $addLockedAcademicYear }}</span>
                                </div>
                                <p class="text-body-sm text-on-surface-variant mt-1 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px]">lock</span>
                                    Tahun ajaran diambil otomatis dari bagian tagihan yang dipilih.
                                </p>
                            </div>
                        @else
                            <div>
                                <label for="add_academic_year" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran <span class="text-error">*</span></label>
                                <select id="add_academic_year" wire:model="addAcademicYear" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">Pilih tahun ajaran</option>
                                    @foreach ($addAcademicYearOptions as $ayOption)
                                        <option value="{{ $ayOption }}">{{ $ayOption }}</option>
                                    @endforeach
                                </select>
                                @error('addAcademicYear') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    @else
                        <p class="text-body-sm text-on-surface-variant flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">info</span>
                            Tagihan ini dibuat sebagai tagihan sekali bayar (tanpa periode bulanan/tahunan).
                        </p>
                    @endif

                    @error('addPeriod') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeAddBill" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="saveAddBill" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus Tagihan -->
    @if($isDeleteOpen && $deleteBillId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Tagihan</h3>
                    <button wire:click="closeDelete" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex flex-col gap-5">
                    <p class="text-body-md text-on-surface">
                        Yakin ingin menghapus tagihan <strong>{{ $deleteTypeName }}</strong> <span class="text-on-surface-variant">({{ $deletePeriodLabel }})</span> senilai
                        <strong>Rp {{ number_format($deleteAmount, 0, ',', '.') }}</strong>?
                    </p>
                    <div class="bg-surface-container-low border border-outline-variant rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-body-sm">
                        <span class="text-on-surface-variant">Sudah Dibayar</span>
                        <span class="{{ $deletePaidAmount > 0 ? 'text-error' : 'text-on-surface' }} font-semibold">Rp {{ number_format($deletePaidAmount, 0, ',', '.') }}</span>
                    </div>
                    @if ($deletePaidAmount > 0)
                        <div class="bg-error-container border border-error text-on-error-container rounded-xl px-4 py-3 text-body-sm">
                            Tagihan tidak dapat dihapus karena sudah memiliki pembayaran.
                        </div>
                    @endif
                    @error('deleteConfirm') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeDelete" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="deleteBill"
                            @disabled($deletePaidAmount > 0)
                            class="{{ $deletePaidAmount > 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-error/90' }} bg-error text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">
                        Hapus Tagihan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
