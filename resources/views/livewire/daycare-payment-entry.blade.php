<div>
    @if(session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-3">
        <a href="{{ route('daycare.index') }}" wire:navigate class="hover:text-primary">Daycare</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">Pembayaran</span>
    </div>

    <div class="flex items-center gap-4 mb-stack-lg">
        <div class="w-11 h-11 rounded-xl bg-primary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-[26px]">payments</span>
        </div>
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface leading-tight">Pembayaran Daycare</h1>
            <p class="text-body-md text-on-surface-variant mt-0.5">Pilih anak Daycare untuk mencatat pembayaran.</p>
        </div>
    </div>

    <div class="border-b border-outline-variant mb-stack-lg">
        <nav class="flex items-center gap-1 overflow-x-auto" aria-label="Bagian pembayaran">
            <button
                type="button"
                wire:click="setActiveTab('pembayaran')"
                class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'pembayaran' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}"
                aria-current="{{ $activeTab === 'pembayaran' ? 'page' : 'false' }}"
            >
                Pembayaran Daycare
                @if($activeTab === 'pembayaran')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
            <button
                type="button"
                wire:click="setActiveTab('history')"
                class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'history' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}"
                aria-current="{{ $activeTab === 'history' ? 'page' : 'false' }}"
            >
                Riwayat Transaksi
                @if($activeTab === 'history')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
        </nav>
    </div>

    {{-- Daycare payment summary + date filter --}}
    <section class="mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl px-5 py-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-headline-sm font-headline-sm text-on-surface">Ringkasan Pemasukan Daycare</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Total pemasukan dan jumlah transaksi pembayaran anak Daycare.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="block text-label-sm font-label-sm text-on-surface-variant" for="daycare-summary-preset">
                    PERIODE
                    <select id="daycare-summary-preset" wire:model.live="summaryPreset" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                        <option value="all">Semua Hari</option>
                        <option value="today">Hari Ini</option>
                        <option value="yesterday">Kemarin</option>
                        <option value="this_month">Bulan Ini</option>
                    </select>
                </label>
                <label class="block text-label-sm font-label-sm text-on-surface-variant" for="daycare-summary-start-date">
                    TANGGAL MULAI
                    <input id="daycare-summary-start-date" type="date" wire:model.live="summaryStartDate" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                    @error('summaryStartDate')<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror
                </label>
                <label class="block text-label-sm font-label-sm text-on-surface-variant" for="daycare-summary-end-date">
                    TANGGAL AKHIR
                    <input id="daycare-summary-end-date" type="date" wire:model.live="summaryEndDate" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                    @error('summaryEndDate')<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-gutter">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 flex flex-col gap-2 shadow-sm">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                        <span class="material-symbols-outlined">payments</span>
                    </div>
                </div>
                <div class="mt-2">
                    <p class="text-on-surface-variant text-body-md font-body-md">Total Pemasukan Daycare</p>
                    <p data-testid="daycare-summary-total-amount" class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($summaryTotal, 0, ',', '.') }}</p>
                </div>
            </div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 flex flex-col gap-2 shadow-sm">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined">receipt_long</span>
                    </div>
                </div>
                <div class="mt-2">
                    <p class="text-on-surface-variant text-body-md font-body-md">Jumlah Transaksi</p>
                    <p data-testid="daycare-summary-total-count" class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($summaryCount, 0, ',', '.') }} Transaksi</p>
                </div>
            </div>
        </div>
    </section>

    @if($activeTab === 'pembayaran')
        @if($selectedChild)
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl px-5 py-5 sm:px-6 shadow-sm mb-stack-lg">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 lg:gap-6">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="w-14 h-14 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold shrink-0">{{ strtoupper(substr($selectedChild->nama_lengkap, 0, 2)) }}</div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-headline-md font-headline-md text-on-surface leading-tight">{{ $selectedChild->nama_lengkap }}</h2>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm {{ $selectedChild->is_active ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' }}"><span class="w-1.5 h-1.5 rounded-full {{ $selectedChild->is_active ? 'bg-secondary' : 'bg-on-surface-variant' }}"></span>{{ $selectedChild->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <p class="text-body-sm text-on-surface-variant mt-1.5">{{ $selectedChild->nama_panggilan }} <span class="mx-1">&bull;</span> Kelas {{ $selectedChild->kelas }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0 lg:justify-end">
                        <button type="button" wire:click="changeChild" class="px-3.5 py-2 text-on-surface-variant font-label-md border border-outline-variant rounded-xl hover:bg-surface-container transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">person_search</span>
                            Ganti Anak
                        </button>
                        <a href="{{ route('daycare.payment.create', $selectedChild) }}" class="px-4 py-2 bg-primary hover:bg-primary/90 text-on-primary font-label-md rounded-xl transition-colors shadow-sm flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[20px]">add</span>
                            Input Pembayaran
                        </a>
                    </div>
                </div>

                <div class="mt-5 pt-5 border-t border-outline-variant">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-y-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-primary-fixed/60 text-primary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">badge</span></div>
                            <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Nama Lengkap</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $selectedChild->nama_lengkap }}</p></div>
                        </div>
                        <div class="flex items-start gap-3 min-w-0 sm:border-l sm:border-outline-variant/60 sm:px-6">
                            <div class="w-9 h-9 rounded-lg bg-primary-fixed/40 text-primary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">child_care</span></div>
                            <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Panggilan &amp; Kelas</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $selectedChild->nama_panggilan }} <span class="text-on-surface-variant">/ Kelas {{ $selectedChild->kelas }}</span></p></div>
                        </div>
                        <div class="flex items-start gap-3 min-w-0 sm:border-l sm:border-outline-variant/60 sm:px-6">
                            <div class="w-9 h-9 rounded-lg bg-secondary-container/60 text-secondary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">group</span></div>
                            <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Status</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $selectedChild->is_active ? 'Aktif' : 'Nonaktif' }}</p></div>
                        </div>
                    </div>
                </div>
            </section>
        @else
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm mb-stack-lg">
                <label for="daycare-payment-search" class="block text-label-md font-label-md text-on-surface mb-1">Cari Anak</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                    <input id="daycare-payment-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Cari nama lengkap atau nama panggilan..." autocomplete="off" class="w-full pl-10 pr-4 py-2.5 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                </div>

                @if(mb_strlen(trim($search)) >= 2)
                    @if($searchResults->isNotEmpty())
                        <div class="mt-3 border border-outline-variant rounded-xl overflow-hidden divide-y divide-outline-variant shadow-sm">
                            @foreach($searchResults as $child)
                                <button type="button" wire:key="daycare-payment-result-{{ $child->id }}" wire:click="selectChild({{ $child->id }})" class="w-full px-4 py-3 text-left hover:bg-surface-container-low transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-on-surface text-body-md truncate">{{ $child->nama_lengkap }}</p>
                                        <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $child->nama_panggilan }} &bull; Kelas {{ $child->kelas }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm {{ $child->is_active ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' }}"><span class="w-1.5 h-1.5 rounded-full {{ $child->is_active ? 'bg-secondary' : 'bg-on-surface-variant' }}"></span>{{ $child->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-3 border border-dashed border-outline-variant rounded-xl px-5 py-6 text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-3xl mb-1 block">person_search</span>
                            <p class="text-body-md">Anak Daycare tidak ditemukan.</p>
                        </div>
                    @endif
                @else
                    <div class="mt-5 border border-dashed border-outline-variant rounded-xl px-6 py-8 text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-4xl mb-2 block">child_care</span>
                        <p class="text-body-md">Cari anak Daycare untuk mencatat pembayaran.</p>
                    </div>
                @endif
            </div>
        @endif
    @else
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm mb-stack-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="md:col-span-2 xl:col-span-1">
                    <label for="daycare-history-search" class="block text-label-md font-label-md text-on-surface mb-1">Cari Transaksi</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                        <input id="daycare-history-search" type="text" wire:model.live.debounce.300ms="historySearch" placeholder="Nama, atau nomor kwitansi..." autocomplete="off" class="w-full pl-10 pr-4 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                    </div>
                </div>
                <div>
                    <label for="daycare-history-bank" class="block text-label-md font-label-md text-on-surface mb-1">Bank</label>
                    <select id="daycare-history-bank" wire:model.live="bankId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Bank</option>
                        @foreach($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->optionLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="daycare-history-status" class="block text-label-md font-label-md text-on-surface mb-1">Status</label>
                    <select id="daycare-history-status" wire:model.live="status" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Status</option>
                        <option value="active">Aktif</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>
                <div>
                    <label for="daycare-history-start-date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Mulai</label>
                    <input id="daycare-history-start-date" type="date" wire:model.live="startDate" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                </div>
                <div>
                    <label for="daycare-history-end-date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Akhir</label>
                    <input id="daycare-history-end-date" type="date" wire:model.live="endDate" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                </div>
            </div>
        </div>

        <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm mb-stack-lg">
            <div class="p-5 border-b border-outline-variant">
                <h2 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Transaksi</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Daftar seluruh transaksi pembayaran anak Daycare.</p>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse min-w-[1200px]">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">
                            <th class="py-3.5 px-3 w-14 whitespace-nowrap text-center">No.</th>
                            <th class="py-3 px-4 whitespace-nowrap">No. Kwitansi</th>
                            <th class="py-3 px-4 whitespace-nowrap">Tanggal TF</th>
                            <th class="py-3 px-4 whitespace-nowrap">Nama &amp; Kelas</th>
                            <th class="py-3 px-4 whitespace-nowrap">Detail Pembayaran</th>
                            <th class="py-3 px-4 whitespace-nowrap">Bank</th>
                            <th class="py-3 px-4 whitespace-nowrap">Total Pembayaran</th>
                            <th class="py-3 px-4 whitespace-nowrap text-center">Status</th>
                            <th class="py-3 px-4 whitespace-nowrap">Diinput Oleh</th>
                            <th class="py-3 px-4 whitespace-nowrap text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse($historyPayments as $payment)
                            <tr wire:key="daycare-history-payment-{{ $payment->id }}" class="hover:bg-surface-container-low transition-colors">
                                <td class="py-4 px-3 text-body-md text-on-surface-variant text-center whitespace-nowrap font-numeric-data">{{ ($historyPayments->currentPage() - 1) * $historyPayments->perPage() + $loop->iteration }}</td>
                                <td class="py-3 px-4 text-body-md font-bold text-on-surface font-numeric-data">
                                    <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                        <span class="whitespace-nowrap">{{ $payment->receipt_number }}</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-800">Daycare</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-body-md text-on-surface-variant whitespace-nowrap">{{ $payment->payment_date?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">
                                    <div class="font-semibold">{{ $payment->child?->nama_lengkap ?? '—' }}</div>
                                    <div class="text-body-sm text-on-surface-variant">Daycare • Kelas {{ $payment->child?->kelas ?? '—' }}</div>
                                </td>
                                <td class="py-3 px-4 text-body-md text-on-surface-variant max-w-[220px] truncate">{{ $payment->detail_summary }}</td>
                                <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">
                                    <div class="font-semibold">{{ $payment->bank?->paymentLabel() ?? '—' }}</div>
                                    @if($payment->bank?->displayAccountNumber())
                                        <div class="text-body-sm text-on-surface-variant font-numeric-data">{{ $payment->bank->displayAccountNumber() }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-body-md font-bold text-on-surface whitespace-nowrap font-numeric-data">Rp {{ number_format((float) $payment->total_amount, 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span>Lunas</span>
                                </td>
                                <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">{{ $payment->creator?->name ?? 'Administrator' }}</td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('daycare.payment.show', $payment) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Lihat Detail / Cetak Kwitansi"><span class="material-symbols-outlined text-[20px]">visibility</span></a>
                                        <a href="{{ route('daycare.payment.edit', $payment) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit Pembayaran"><span class="material-symbols-outlined text-[20px]">edit</span></a>
                                        <button type="button" wire:click="confirmDelete({{ $payment->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus Transaksi"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="p-8 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined text-4xl mb-2 block">receipt_long</span>
                                    <p>{{ trim($historySearch) !== '' || $bankId !== '' || $status !== '' || $startDate !== '' || $endDate !== '' ? 'Tidak ada transaksi yang sesuai filter.' : 'Belum ada transaksi pembayaran.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($historyPayments->hasPages())
                <div class="p-4 border-t border-outline-variant">
                    {{ $historyPayments->links(data: ['scrollTo' => false]) }}
                </div>
            @endif
        </section>
    @endif

    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="daycare-entry-delete-title">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-full bg-error-container text-error flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined">delete_forever</span>
                    </div>
                    <h3 id="daycare-entry-delete-title" class="text-headline-sm font-headline-sm text-on-surface">Hapus transaksi {{ $deletingReceiptNumber }}?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2">Transaksi akan dihapus permanen dan tidak lagi dihitung dalam Dashboard maupun rekap Bank.</p>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3">
                    <button type="button" wire:click="cancelDelete" class="px-5 py-2.5 text-on-surface-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button type="button" wire:click="delete" class="px-5 py-2.5 bg-error text-on-error rounded-xl font-label-lg hover:opacity-90">Hapus Permanen</button>
                </div>
            </div>
        </div>
    @endif
</div>
