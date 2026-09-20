<div>
    <section class="mb-stack-md rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-stretch">
            <div class="flex items-center gap-3 lg:w-[280px] lg:shrink-0 lg:border-r lg:border-outline-variant lg:pr-5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-fixed/60 text-primary">
                    <span class="material-symbols-outlined text-[20px]">tune</span>
                </div>
                <div class="min-w-0">
                    <p class="text-label-lg font-label-lg text-on-surface">Filter Dashboard</p>
                    <p class="mt-0.5 text-body-sm leading-5 text-on-surface-variant">Pilih unit dan periode untuk menampilkan data.</p>
                </div>
            </div>
            <div class="grid min-w-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block min-w-0 text-label-sm font-label-sm text-on-surface-variant">
                    UNIT
                    <select wire:model.live="operationalUnit" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                        @foreach($operationalUnitOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block min-w-0 text-label-sm font-label-sm text-on-surface-variant">
                    PERIODE
                    <select wire:model.live="operationalPeriod" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                        @foreach($operationalPeriodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if($operationalPeriod === 'custom')
                    <label class="block min-w-0 text-label-sm font-label-sm text-on-surface-variant">
                        TANGGAL MULAI
                        <input type="date" wire:model.live="operationalStartDate" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                        @error('operationalStartDate')<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="block min-w-0 text-label-sm font-label-sm text-on-surface-variant">
                        TANGGAL AKHIR
                        <input type="date" wire:model.live="operationalEndDate" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                        @error('operationalEndDate')<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror
                    </label>
                @endif
            </div>
        </div>
        <div wire:loading.delay wire:target="operationalUnit,operationalPeriod,operationalStartDate,operationalEndDate" class="mt-2 text-xs text-on-surface-variant">Memperbarui data operasional...</div>
    </section>

    <!-- Summary Cards Row -->
    <section class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-gutter">
        <!-- Card 1: Total Pemasukan -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">payments</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Pemasukan</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</p>
            </div>
        </div>

        <!-- Card 2: Total Transaksi -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">receipt_long</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Transaksi</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($totalTransaksi, 0, ',', '.') }} Transaksi</p>
            </div>
        </div>

        <!-- Card 3: Active Population -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">groups</span>
                </div>
            </div>
            <div class="mt-2">
                @if($operationalUnit === 'all')
                    <p class="text-on-surface-variant text-body-md font-body-md">Total Peserta Aktif</p>
                    <div class="mt-1 flex flex-wrap items-baseline gap-x-4 gap-y-1 text-on-surface">
                        <p class="font-numeric-data text-title-lg font-title-lg tracking-wider">{{ number_format($totalSiswaAktif, 0, ',', '.') }} <span class="text-body-sm font-body-sm text-on-surface-variant">Siswa</span></p>
                        <p class="font-numeric-data text-title-lg font-title-lg tracking-wider">{{ number_format($activeDaycareChildren, 0, ',', '.') }} <span class="text-body-sm font-body-sm text-on-surface-variant">Anak Daycare</span></p>
                    </div>
                @elseif($operationalUnit === 'daycare')
                    <p class="text-on-surface-variant text-body-md font-body-md">Total Anak Daycare</p>
                    <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($activeDaycareChildren, 0, ',', '.') }} Anak</p>
                @else
                    <p class="text-on-surface-variant text-body-md font-body-md">Total Siswa Aktif</p>
                    <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($totalSiswaAktif, 0, ',', '.') }} Siswa</p>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-stack-lg overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
        <div class="flex flex-col gap-3 border-b border-outline-variant p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <p class="text-label-sm font-label-sm uppercase tracking-[0.14em] text-primary">Capaian Tagihan Bulan Ini</p>
                <h2 class="mt-1 text-headline-sm font-headline-sm text-on-surface">{{ $targetArrearsSummary['period_label'] }}</h2>
                <p class="mt-1 text-body-sm text-on-surface-variant">Target dan tunggakan tagihan bulanan siswa.</p>
            </div>
            <a href="{{ $targetArrearsUrl }}" class="inline-flex items-center gap-1 self-start whitespace-nowrap text-label-lg font-label-lg text-primary transition-colors hover:text-primary/80 sm:self-auto">
                Lihat Target &amp; Tunggakan
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </a>
        </div>

        @if($targetArrearsSummary['bill_count'] === 0)
            <div class="px-5 py-8 text-center sm:px-6">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant">assignment_late</span>
                <p class="mt-3 text-body-md font-medium text-on-surface">Belum ada tagihan bulanan untuk {{ $targetArrearsSummary['period_label'] }}.</p>
                <p class="mt-1 text-body-sm text-on-surface-variant">Buka laporan lengkap untuk melihat periode lainnya.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
                <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                    <p class="text-label-sm font-label-sm tracking-wider text-on-surface-variant">TARGET</p>
                    <p class="mt-2 whitespace-nowrap font-numeric-data text-headline-sm font-headline-sm text-on-surface">Rp {{ number_format($targetArrearsSummary['totals']['target'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                    <p class="text-label-sm font-label-sm tracking-wider text-on-surface-variant">TERBAYAR</p>
                    <p class="mt-2 whitespace-nowrap font-numeric-data text-headline-sm font-headline-sm text-on-surface">Rp {{ number_format($targetArrearsSummary['totals']['paid'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-primary/30 bg-primary-fixed p-4">
                    <p class="text-label-sm font-label-sm tracking-wider text-on-primary-fixed">TUNGGAKAN</p>
                    <p class="mt-2 whitespace-nowrap font-numeric-data text-headline-sm font-headline-sm text-on-primary-fixed">Rp {{ number_format($targetArrearsSummary['totals']['outstanding'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-label-sm font-label-sm tracking-wider text-on-surface-variant">CAPAIAN</p>
                        <p class="whitespace-nowrap font-numeric-data text-title-md font-title-md text-on-surface">{{ $targetArrearsSummary['totals']['achievement_label'] }}</p>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-outline-variant/40">
                        <div class="h-full rounded-full bg-primary" style="width: {{ $targetArrearsSummary['totals']['achievement_percentage'] }}%"></div>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <!-- Bank Accounts Row -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-gutter mt-stack-lg">
        <div class="flex flex-col gap-stack-md lg:col-span-12">
            <div class="flex items-center justify-between">
                <h2 class="text-headline-sm font-headline-sm text-on-surface">Rekap Penerimaan</h2>
                <button class="text-secondary hover:text-secondary-container transition-colors">
                    <span class="material-symbols-outlined">more_horiz</span>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                @forelse($banks as $bank)
                    @php
                        $palette = ['#005E6A', '#0060A9', '#00529C', '#005A8D', '#E85C19', '#5B2C8E', '#2E7D32', '#C62828'];
                        $bg = $palette[crc32($bank->name) % count($palette)];
                        $letter = strtoupper(mb_substr($bank->name, 0, 1));
                        $totalBank = $bankTotals[$bank->id]['combined_total'];
                    @endphp
                    <div class="bg-surface-container-lowest border border-outline-variant p-4 rounded-xl flex items-center hover:shadow-[0_4px_12px_rgba(0,0,0,0.05)] transition-all cursor-pointer group">
                        <div class="flex flex-col gap-4 w-full">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center font-bold shrink-0" style="background-color: {{ $bg }}1A; color: {{ $bg }};">
                                    {{ $letter }}
                                </div>
                                <div>
                                    <p class="font-label-md text-label-md text-on-surface group-hover:text-secondary transition-colors">{{ $bank->name }}</p>
                                    <p class="text-body-sm font-body-sm text-on-surface-variant">{{ $bank->accountSummary() ?? $bank->typeLabel() }}</p>
                                </div>
                            </div>
                            <div class="pt-2 border-t border-outline-variant/30">
                                <p class="font-numeric-data text-numeric-data text-on-surface tracking-wider">Rp {{ number_format($totalBank, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center text-on-surface-variant py-8">
                        <span class="material-symbols-outlined text-4xl mb-2">account_balance</span>
                        <p>Belum ada bank terdaftar.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- Recent Transactions Table -->
    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mt-stack-lg shadow-sm">
        <div class="p-5 sm:p-6 border-b border-outline-variant flex justify-between items-center">
            <div><h2 class="text-headline-sm font-headline-sm text-on-surface">Transaksi Terbaru</h2><p class="text-body-sm text-on-surface-variant mt-1">Pembayaran Siswa dan Daycare terbaru.</p></div>
            <a href="{{ route('pembayaran.index', ['tab' => 'history']) }}" class="inline-flex items-center gap-1 text-label-lg font-label-lg text-primary hover:text-primary/80 transition-colors">
                Lihat Semua
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </a>
        </div>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">
                        <th class="py-3.5 px-3 w-14 whitespace-nowrap text-center">No.</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">No. Kwitansi</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">Tanggal TF</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">Nama &amp; Kelas</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">Detail Pembayaran</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">Penerimaan</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">Total</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap text-center">Status</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap">Diinput Oleh</th>
                        <th class="py-3.5 px-4 sm:px-6 whitespace-nowrap text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse($recentTransactions as $transaction)
                        <tr wire:key="recent-transaction-{{ $transaction->type }}-{{ $transaction->sourceId }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center whitespace-nowrap font-numeric-data">{{ $loop->iteration }}</td>
                            <td class="py-4 px-4 sm:px-6 text-body-md font-bold text-on-surface font-numeric-data">
                                <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                    <span class="whitespace-nowrap">{{ $transaction->receiptNumber }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $transaction->type === 'daycare' ? 'bg-purple-100 text-purple-800' : ($transaction->type === 'prospective' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800') }}">{{ $transaction->typeLabel() }}</span>
                                    @if($transaction->isManual)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-800">Manual</span>@endif
                                </div>
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-body-md text-on-surface-variant whitespace-nowrap">
                                <div>{{ $transaction->paymentDate->locale('id')->translatedFormat('d M Y') }}</div>
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-body-md text-on-surface whitespace-nowrap">
                                <div class="font-bold text-on-surface">{{ $transaction->name }}</div>
                                <div class="text-body-sm text-on-surface-variant">{{ $transaction->secondaryInfo }}</div>
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-body-md text-on-surface-variant max-w-[200px] truncate">
                                {{ $transaction->description }}
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-body-md text-on-surface font-semibold whitespace-nowrap">
                                <div>{{ $transaction->bank }}</div>
                                @if($transaction->bankAccountNumber)
                                    <div class="text-body-sm font-normal text-on-surface-variant font-numeric-data">{{ $transaction->bankAccountNumber }}</div>
                                @endif
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-body-md font-bold text-on-surface whitespace-nowrap font-numeric-data">
                                Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-center whitespace-nowrap">
                                @if($transaction->status === 'Dibatalkan')
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container">
                                        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                        Dibatalkan
                                    </span>
                                @elseif($transaction->status === 'Tunggakan')
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container">
                                        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                        Tunggakan
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                        {{ $transaction->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-body-md text-on-surface whitespace-nowrap">
                                <div class="font-medium">{{ $transaction->creator }}</div>
                                <div class="text-body-sm text-on-surface-variant">Admin</div>
                            </td>
                            <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ $transaction->detailUrl }}" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Lihat Detail / Cetak">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">receipt_long</span>
                                <p>Belum ada transaksi pembayaran.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
