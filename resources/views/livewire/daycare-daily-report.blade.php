<div class="annur-report-page space-y-stack-lg">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-label-md font-label-md uppercase tracking-[0.16em] text-primary">Daycare</p>
            <h1 class="mt-1 text-display-sm font-display-sm text-on-surface">Laporan Penerimaan</h1>
            <p class="mt-1 text-body-md text-on-surface-variant">Rekap pembayaran anak Daycare berdasarkan tanggal penerimaan.</p>
        </div>

        @if($activeTab === 'daily')
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-row">
                <a
                    href="{{ route('daycare.report.daily.riwayat.pdf', ['start_date' => $appliedStartDate, 'end_date' => $appliedEndDate]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    Cetak Riwayat Transaksi
                </a>
                <a
                    href="{{ route('daycare.report.daily.export', ['start_date' => $appliedStartDate, 'end_date' => $appliedEndDate]) }}"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Unduh Excel
                </a>
                <a
                    href="{{ route('daycare.report.daily.pdf', ['start_date' => $appliedStartDate, 'end_date' => $appliedEndDate]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">print</span>
                    Cetak PDF
                </a>
            </div>
        @elseif($activeTab === 'monthly')
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-row">
                <a
                    href="{{ route('daycare.report.monthly.export', ['month' => $reportMonth, 'year' => $reportYear]) }}"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Unduh Excel
                </a>
                <a
                    href="{{ route('daycare.report.monthly.pdf', ['month' => $reportMonth, 'year' => $reportYear]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">print</span>
                    Cetak PDF
                </a>
            </div>
        @endif
    </div>

    <div class="border-b border-outline-variant">
        <nav class="flex items-center gap-1 overflow-x-auto" aria-label="Jenis laporan">
            <button type="button" wire:click="setActiveTab('daily')" class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'daily' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Laporan Harian
                @if($activeTab === 'daily')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
            <button type="button" wire:click="setActiveTab('monthly')" class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'monthly' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Laporan Bulanan
                @if($activeTab === 'monthly')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
        </nav>
    </div>

    @if($activeTab === 'monthly')
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
            <div>
                <p class="text-label-md font-label-md text-on-surface">Periode Laporan</p>
                <p class="mt-1 max-w-3xl text-body-sm text-on-surface-variant">Penerimaan Daycare dikelompokkan per tanggal sesuai tanggal pembayaran, lalu dirinci per rekening bank dan tunai.</p>
                <p class="mt-2 text-body-sm text-on-surface-variant">Periode: <strong class="text-on-surface">{{ $monthlyReport['month_label'] }}</strong> <span aria-hidden="true">&bull;</span> Unit: <strong class="text-on-surface">Daycare</strong></p>
            </div>
            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label for="daycare-report-month" class="block text-body-sm text-on-surface-variant">Bulan</label>
                        <select id="daycare-report-month" wire:model.live="reportMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($monthOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @error('reportMonth') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label for="daycare-report-year" class="block text-body-sm text-on-surface-variant">Tahun</label>
                        <select id="daycare-report-year" wire:model.live="reportYear" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($yearOptions as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                        @error('reportYear') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                </div>
        </section>

        @if(! $monthlyReport['has_payments'])
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">calendar_month</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada transaksi Daycare pada periode yang dipilih.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Pilih bulan lain untuk melihat penerimaan yang sudah tercatat.</p>
                <p class="mt-2 font-numeric-data text-label-lg font-label-lg text-on-surface">Total penerimaan: Rp 0</p>
            </section>
        @else
            <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">PENERIMAAN BANK</h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-left">
                        <caption class="sr-only">Penerimaan Bank per Tanggal</caption>
                        <thead>
                            <tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                <th class="min-w-[180px] px-3 py-2 sm:px-4">Tanggal</th>
                                <th class="min-w-[150px] px-3 py-2">Bank</th>
                                @foreach($monthlyReport['categories'] as $category)
                                    <th class="min-w-[110px] px-3 py-2 text-right">{{ $category['name'] }}</th>
                                @endforeach
                                <th class="min-w-[120px] px-3 py-2 text-right sm:px-4">Total</th>
                                <th class="min-w-[130px] px-3 py-2 text-right sm:px-4">Total Harian</th>
                            </tr>
                        </thead>
                        @if($monthlyReport['bank']['account_count'] === 0)
                            <tbody><tr><td colspan="{{ count($monthlyReport['categories']) + 4 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Belum ada rekening bank yang dikonfigurasi.</td></tr></tbody>
                        @elseif(count($monthlyReport['bank']['dates']) === 0)
                            <tbody><tr><td colspan="{{ count($monthlyReport['categories']) + 4 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Tidak ada transaksi bank pada bulan ini.</td></tr></tbody>
                        @else
                            @foreach($monthlyReport['bank']['dates'] as $dateGroup)
                                <tbody class="divide-y divide-outline-variant border-t border-outline-variant">
                                    @foreach($dateGroup['banks'] as $bankRow)
                                        <tr class="align-top">
                                            @if($loop->first)
                                                <td rowspan="{{ $dateGroup['bank_count'] }}" class="px-3 py-2 align-middle text-label-md font-label-md text-on-surface sm:px-4">
                                                    {{ $dateGroup['date_label'] }}
                                                </td>
                                            @endif
                                            <td class="px-3 py-2 text-body-sm font-medium text-on-surface">{{ $bankRow['bank_label'] }}</td>
                                            @foreach($monthlyReport['categories'] as $category)
                                                <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $bankRow['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bankRow['amounts'][$category['key']], 0, ',', '.') : '' }}</td>
                                            @endforeach
                                            <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold text-on-surface sm:px-4">{{ $bankRow['total'] > 0 ? 'Rp '.number_format($bankRow['total'], 0, ',', '.') : '' }}</td>
                                            @if($loop->first)
                                                <td rowspan="{{ $dateGroup['bank_count'] }}" class="whitespace-nowrap px-3 py-2 text-right align-middle font-numeric-data font-bold text-on-surface sm:px-4">{{ $dateGroup['total'] > 0 ? 'Rp '.number_format($dateGroup['total'], 0, ',', '.') : '' }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        @endif
                        <tfoot>
                            <tr class="border-t-2 border-primary/30 bg-primary-fixed text-label-md font-label-md text-on-primary-fixed">
                                <td class="px-3 py-2 sm:px-4" colspan="2">TOTAL PENERIMAAN BANK</td>
                                @foreach($monthlyReport['categories'] as $category)
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $monthlyReport['bank']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($monthlyReport['bank']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>
                                @endforeach
                                <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $monthlyReport['bank']['total'] > 0 ? 'Rp '.number_format($monthlyReport['bank']['total'], 0, ',', '.') : '' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $monthlyReport['bank']['total'] > 0 ? 'Rp '.number_format($monthlyReport['bank']['total'], 0, ',', '.') : '' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="mt-4 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">PENERIMAAN TUNAI</h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max border-collapse text-left">
                        <caption class="sr-only">Penerimaan Tunai per Tanggal</caption>
                        <thead>
                            <tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                <th class="min-w-[180px] px-3 py-2 sm:px-4">Tanggal</th>
                                @foreach($monthlyReport['categories'] as $category)
                                    <th class="min-w-[110px] px-3 py-2 text-right">{{ $category['name'] }}</th>
                                @endforeach
                                <th class="min-w-[120px] px-3 py-2 text-right sm:px-4">Total</th>
                            </tr>
                        </thead>
                        @if(count($monthlyReport['cash']['dates']) === 0)
                            <tbody><tr><td colspan="{{ count($monthlyReport['categories']) + 1 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Tidak ada transaksi tunai pada bulan ini.</td></tr></tbody>
                        @else
                            <tbody class="divide-y divide-outline-variant">
                                @foreach($monthlyReport['cash']['dates'] as $cashDate)
                                    <tr>
                                        <td class="px-3 py-2 text-label-md font-label-md text-on-surface sm:px-4">{{ $cashDate['date_label'] }}</td>
                                        @foreach($monthlyReport['categories'] as $category)
                                            <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $cashDate['amounts'][$category['key']] > 0 ? 'Rp '.number_format($cashDate['amounts'][$category['key']], 0, ',', '.') : '' }}</td>
                                        @endforeach
                                        <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold text-on-surface sm:px-4">{{ $cashDate['total'] > 0 ? 'Rp '.number_format($cashDate['total'], 0, ',', '.') : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endif
                        <tfoot>
                            <tr class="border-t-2 border-primary/30 bg-primary-fixed text-label-md font-label-md text-on-primary-fixed">
                                <td class="px-3 py-2 sm:px-4">TOTAL PENERIMAAN TUNAI</td>
                                @foreach($monthlyReport['categories'] as $category)
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $monthlyReport['cash']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($monthlyReport['cash']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>
                                @endforeach
                                <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $monthlyReport['cash']['total'] > 0 ? 'Rp '.number_format($monthlyReport['cash']['total'], 0, ',', '.') : '' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="mt-4 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">RINGKASAN TOTAL</h2>
                <dl class="divide-y divide-outline-variant text-body-sm">
                    <div class="flex items-center justify-between gap-6 px-4 py-2"><dt>TOTAL PENERIMAAN BANK</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $monthlyReport['bank']['total'] > 0 ? 'Rp '.number_format($monthlyReport['bank']['total'], 0, ',', '.') : '' }}</dd></div>
                    <div class="flex items-center justify-between gap-6 px-4 py-2"><dt>TOTAL PENERIMAAN TUNAI</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $monthlyReport['cash']['total'] > 0 ? 'Rp '.number_format($monthlyReport['cash']['total'], 0, ',', '.') : '' }}</dd></div>
                    <div class="flex items-center justify-between gap-6 bg-primary-fixed px-4 py-2 text-label-md font-label-md text-on-primary-fixed"><dt>GRAND TOTAL</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $monthlyReport['grand_total'] > 0 ? 'Rp '.number_format($monthlyReport['grand_total'], 0, ',', '.') : '' }}</dd></div>
                </dl>
            </section>
        @endif
    @else
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
                <div>
                    <p class="text-label-md font-label-md text-on-surface">Periode Laporan</p>
                    <p class="mt-0.5 text-body-sm text-on-surface-variant">Tanggal mengikuti tanggal pembayaran pada transaksi Daycare.</p>
                    <p class="mt-2 text-body-sm text-on-surface-variant">{{ $report['period_title'] }}: <strong class="text-on-surface">{{ $report['period_label'] }}</strong> <span aria-hidden="true">&bull;</span> Unit: <strong class="text-on-surface">Daycare</strong></p>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label for="daycare-report-start-date" class="block text-body-sm text-on-surface-variant">Dari Tanggal</label>
                        <input id="daycare-report-start-date" type="date" wire:model.live="reportStartDate" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @error('reportStartDate') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label for="daycare-report-end-date" class="block text-body-sm text-on-surface-variant">Sampai Tanggal</label>
                        <input id="daycare-report-end-date" type="date" wire:model.live="reportEndDate" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @error('reportEndDate') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <p class="text-label-md text-on-surface-variant">Tunai / Cash</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-surface">Rp {{ number_format($report['total_cash'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <p class="text-label-md text-on-surface-variant">Transfer / Debet</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-surface">Rp {{ number_format($report['total_transfer'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-primary/30 bg-primary-fixed p-5">
                <p class="text-label-md text-on-primary-fixed-variant">Total Penerimaan</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-primary-fixed">Rp {{ number_format($report['grand_total'], 0, ',', '.') }}</p>
                <p class="mt-1 text-body-sm text-on-primary-fixed-variant">{{ $report['transaction_count'] }} transaksi</p>
            </div>
        </section>

        @if(! $report['has_payments'])
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">receipt_long</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada transaksi Daycare pada periode yang dipilih.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Pilih tanggal lain untuk melihat penerimaan yang sudah tercatat.</p>
            </section>
        @else
            @foreach($report['channels'] as $channel)
                <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <header class="flex flex-col items-start gap-2 border-b border-outline-variant bg-surface-container-low px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary">{{ $channel['key'] === 'cash' ? 'payments' : 'account_balance' }}</span>
                            <h2 class="text-headline-sm font-headline-sm text-on-surface">{{ $channel['label'] }}</h2>
                        </div>
                        <p class="font-numeric-data text-label-lg font-label-lg text-on-surface">Rp {{ number_format($channel['total'], 0, ',', '.') }}</p>
                    </header>

                    @forelse($channel['banks'] as $bank)
                        <div class="border-b border-outline-variant last:border-b-0">
                            <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div>
                                    <h3 class="text-label-lg font-label-lg text-on-surface">{{ $bank['bank_name'] }}</h3>
                                    <p class="text-body-sm text-on-surface-variant">{{ $bank['bank_label'] }}</p>
                                </div>
                                <p class="font-numeric-data text-body-md font-bold text-on-surface">Rp {{ number_format($bank['total'], 0, ',', '.') }}</p>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] border-collapse text-left">
                                    <colgroup>
                                        <col class="w-[55%]">
                                        <col class="w-[15%]">
                                        <col class="w-[30%]">
                                    </colgroup>
                                    <thead>
                                        <tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                            <th class="px-5 py-3 sm:px-6">Jenis Penerimaan</th>
                                            <th class="px-3 py-3 text-center">Rincian</th>
                                            <th class="px-5 py-3 text-right sm:px-6">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline-variant">
                                        @foreach($bank['categories'] as $category)
                                            <tr>
                                                <td class="px-5 py-3 font-medium text-on-surface sm:px-6">{{ $category['name'] }}</td>
                                                <td class="px-3 py-3 text-center font-numeric-data text-on-surface-variant">{{ $category['count'] }}</td>
                                                <td class="px-5 py-3 text-right font-numeric-data font-bold text-on-surface sm:px-6">Rp {{ number_format($category['total'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-center text-body-md text-on-surface-variant">Tidak ada penerimaan melalui kanal ini.</p>
                    @endforelse
                </section>
            @endforeach
        @endif
    @endif
</div>
