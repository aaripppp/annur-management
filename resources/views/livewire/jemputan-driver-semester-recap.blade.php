<div class="annur-report-page min-w-0 space-y-stack-lg">
    @php
        $formatAmount = static fn (float|int|null $amount): string => $amount === null
            ? '—'
            : 'Rp '.number_format((float) $amount, 0, ',', '.');
        $formatPercentage = static fn (float|int|null $value): string => $value === null
            ? 'Belum diatur'
            : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    @endphp

    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-label-md font-label-md uppercase tracking-[0.16em] text-primary">Jemputan</p>
            <h1 class="mt-1 text-display-sm font-display-sm text-on-surface">Rekap 6 Bulan</h1>
            <p class="mt-1 text-body-md text-on-surface-variant">Rekap pendapatan sopir dan fee yayasan per semester.</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @if($report !== null)
                <span class="inline-flex w-fit items-center rounded-full px-3 py-1.5 text-label-md font-label-md {{ $report['is_final'] ? 'bg-primary-fixed text-on-primary-fixed' : 'bg-error-container text-on-error-container' }}">
                    {{ $report['is_final'] ? 'FINAL' : 'BELUM FINAL' }}
                </span>
            @endif
            <button
                type="button"
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                @disabled($report === null)
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span class="material-symbols-outlined text-[20px]">download</span>
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Menyiapkan...</span>
            </button>
        </div>
    </header>
    @error('export') <p class="text-right text-body-sm text-error">{{ $message }}</p> @enderror

    <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="min-w-0">
                <label for="semester-recap-year" class="block text-body-sm text-on-surface-variant">Tahun Ajaran</label>
                <select id="semester-recap-year" wire:model.live="selectedAcademicYearId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Pilih Tahun Ajaran</option>
                    @foreach($academicYears as $academicYear)
                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                    @endforeach
                </select>
                @error('selectedAcademicYearId') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="semester-recap-level" class="block text-body-sm text-on-surface-variant">Jenjang / Unit</label>
                <select id="semester-recap-level" wire:model.live="selectedSchoolLevel" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua Jenjang</option>
                    @foreach($schoolLevels as $schoolLevel)
                        <option value="{{ $schoolLevel->value }}">{{ $schoolLevel->value }}</option>
                    @endforeach
                </select>
                @error('selectedSchoolLevel') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="semester-recap-semester" class="block text-body-sm text-on-surface-variant">Semester</label>
                <select id="semester-recap-semester" wire:model.live="selectedSemester" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="1">Semester 1 · Juli–Desember</option>
                    <option value="2">Semester 2 · Januari–Juni</option>
                </select>
                @error('selectedSemester') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    @if($report !== null)
        <section aria-label="Catatan perhitungan" class="space-y-3">
            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                <p class="text-body-sm leading-relaxed text-on-surface-variant">Gaji sopir dihitung berdasarkan seluruh tagihan Jemputan, termasuk siswa yang belum membayar. Tagihan yang belum dibayar ditanggung sementara oleh sekolah dan tetap masuk perhitungan gaji.</p>
            </div>

            @if($report['warnings']['allocation']['total_count'] > 0)
                <div class="rounded-xl border border-error/30 bg-error-container/40 px-4 py-3 text-body-sm text-on-error-container">
                    <p class="font-semibold">Alokasi Multi-Sopir belum final.</p>
                    <p class="mt-1">{{ $report['warnings']['allocation']['missing_count'] }} missing, {{ $report['warnings']['allocation']['stale_count'] }} stale, dan {{ $report['warnings']['allocation']['orphan_count'] }} orphan. Tagihan penuh tetap ditampilkan pada setiap sopir terkait agar potensi double count tidak tersembunyi.</p>
                </div>
            @endif

            @if($report['warnings']['missing_rate_count'] > 0)
                <div class="rounded-xl border border-error/30 bg-error-container/40 px-4 py-3 text-body-sm text-on-error-container">
                    <p class="font-semibold">Persentase potongan yayasan belum diatur.</p>
                    <p class="mt-1">Terdapat {{ $report['warnings']['missing_rate_count'] }} periode sopir tanpa persentase. Fee, pendapatan sopir, dan total terkait tidak dianggap nol dan ditampilkan sebagai “—”.</p>
                </div>
            @endif

            @if($report['warnings']['legacy_rate_count'] > 0)
                <div class="rounded-xl border border-tertiary/30 bg-tertiary-container/40 px-4 py-3 text-body-sm text-on-tertiary-container">
                    <p class="font-semibold">Sebagian persentase masih memakai data legacy.</p>
                    <p class="mt-1">Terdapat {{ $report['warnings']['legacy_rate_count'] }} periode sopir yang tanggal efektif historisnya belum terikat pada bulan tertentu.</p>
                </div>
            @endif

            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3 text-body-sm text-on-surface-variant">
                <p><span class="font-semibold text-on-surface">Catatan membership historis:</span> pembagian sopir menggunakan membership aktif saat laporan dibuka; perubahan membership lama belum direkonstruksi sebagai kondisi per bulan.</p>
            </div>
        </section>

        @if($report['rows'] === [])
            <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">group_off</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada data sopir pada filter ini.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Pilih tahun ajaran atau jenjang lain untuk menampilkan rekap.</p>
            </section>
        @else
            <section class="min-w-0 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                <header class="border-b border-outline-variant px-5 py-4 sm:px-6">
                    <h2 class="text-title-md font-title-md text-on-surface">Tahun Ajaran {{ $report['academic_year']['label'] }} · Semester {{ $report['filters']['semester'] }}</h2>
                    <p class="mt-1 text-body-sm text-on-surface-variant">Fee dibulatkan per sopir per bulan sebelum dijumlahkan ke total semester.</p>
                </header>

                <div class="overflow-x-auto overscroll-x-contain">
                    <table data-semester-recap-table class="min-w-[1600px] border-collapse text-left text-body-sm leading-5">
                        <thead class="uppercase tracking-wider text-on-surface-variant">
                            <tr class="border-b-2 border-primary bg-surface-container-low">
                                <th class="w-14 min-w-14 border-r border-outline-variant px-3 py-3.5 text-center align-middle">No</th>
                                <th class="w-64 min-w-64 border-r border-outline-variant px-4 py-3.5 align-middle">Nama Sopir</th>
                                <th class="w-40 min-w-40 border-r-2 border-outline px-4 py-3.5 align-middle">Pembayaran</th>
                                @foreach($report['months'] as $month)
                                    <th class="w-40 min-w-40 whitespace-nowrap border-r border-outline-variant px-3 py-3.5 text-right align-middle">{{ $month['label'] }}</th>
                                @endforeach
                                <th class="w-40 min-w-40 whitespace-nowrap px-4 py-3.5 text-right align-middle">Total</th>
                            </tr>
                        </thead>
                        <tbody class="text-on-surface">
                            @foreach($report['rows'] as $index => $row)
                                <tr class="h-14 border-b border-outline-variant bg-surface-container-lowest">
                                    <td rowspan="3" class="border-r border-outline-variant bg-surface-container-lowest px-3 py-3 text-center align-middle font-numeric-data">{{ $index + 1 }}</td>
                                    <td rowspan="3" class="border-r border-outline-variant bg-surface-container-lowest px-4 py-3 align-middle font-medium">
                                        {{ $row['driver_name'] }}
                                        @unless($row['is_active'])
                                            <span class="mt-1 block text-label-sm text-on-surface-variant">Nonaktif</span>
                                        @endunless
                                    </td>
                                    <th class="whitespace-nowrap border-r-2 border-outline bg-primary-fixed/25 px-4 py-3 align-middle font-medium">Sopir</th>
                                    @foreach($report['months'] as $month)
                                        <td class="whitespace-nowrap border-r border-outline-variant bg-primary-fixed/10 px-3 py-3 text-right align-middle font-numeric-data">{{ $formatAmount($row['months'][$month['key']]['driver_income']) }}</td>
                                    @endforeach
                                    <td class="whitespace-nowrap bg-primary-fixed/10 px-4 py-3 text-right align-middle font-numeric-data font-semibold">{{ $formatAmount($row['totals']['driver_income']) }}</td>
                                </tr>
                                <tr class="h-14 border-b border-outline-variant bg-surface-container-lowest">
                                    <th class="whitespace-nowrap border-r-2 border-outline bg-tertiary-fixed/25 px-4 py-3 align-middle font-medium">Fee Yayasan</th>
                                    @foreach($report['months'] as $month)
                                        @php($monthData = $row['months'][$month['key']])
                                        <td data-fee-cell class="whitespace-nowrap border-r border-outline-variant bg-tertiary-fixed/10 px-3 py-3 text-right align-middle font-numeric-data" title="{{ $monthData['percentage_source'] === \App\Services\JemputanDriverDeductionRateResolver::SOURCE_LEGACY ? 'Persentase legacy; bulan efektif historis belum tetap' : 'Persentase periode ini' }}">
                                            @if($monthData['percentage'] === null)
                                                <span class="text-body-sm text-on-surface-variant">Belum diatur</span>
                                            @else
                                                <span class="inline-flex items-baseline justify-end gap-1 whitespace-nowrap">
                                                    <span class="font-medium text-on-surface">{{ $formatAmount($monthData['foundation_fee']) }}</span>
                                                    <span class="text-label-sm text-on-surface-variant">({{ $formatPercentage($monthData['percentage']) }})</span>
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="whitespace-nowrap bg-tertiary-fixed/10 px-4 py-3 text-right align-middle font-numeric-data font-semibold">{{ $formatAmount($row['totals']['foundation_fee']) }}</td>
                                </tr>
                                <tr class="h-14 border-b-2 border-outline bg-surface-container-lowest">
                                    <th class="whitespace-nowrap border-r-2 border-outline bg-secondary-container/60 px-4 py-3 align-middle font-semibold">Total</th>
                                    @foreach($report['months'] as $month)
                                        <td class="whitespace-nowrap border-r border-outline-variant bg-secondary-container/25 px-3 py-3 text-right align-middle font-numeric-data font-semibold">{{ $formatAmount($row['months'][$month['key']]['total']) }}</td>
                                    @endforeach
                                    <td class="whitespace-nowrap bg-secondary-container/25 px-4 py-3 text-right align-middle font-numeric-data font-bold">{{ $formatAmount($row['totals']['total']) }}</td>
                                </tr>
                            @endforeach

                            @foreach([
                                ['Sopir', 'driver_income', 'bg-primary-fixed/25'],
                                ['Fee Yayasan', 'foundation_fee', 'bg-tertiary-fixed/25'],
                                ['Total', 'total', 'bg-secondary-container/60'],
                            ] as $recapIndex => [$label, $key, $background])
                                <tr class="h-14 {{ $recapIndex === 2 ? 'border-t border-outline border-b-2 border-primary' : 'border-b border-outline-variant' }} font-semibold">
                                    @if($recapIndex === 0)
                                        <th rowspan="3" colspan="2" class="border-r border-outline bg-surface-container-high px-4 py-3 align-middle text-title-md font-title-md">REKAP</th>
                                    @endif
                                    <th class="whitespace-nowrap border-r-2 border-outline px-4 py-3 align-middle {{ $background }}">{{ $label }}</th>
                                    @foreach($report['months'] as $month)
                                        <td class="whitespace-nowrap border-r border-outline-variant px-3 py-3 text-right align-middle font-numeric-data {{ $background }}">{{ $formatAmount($report['recap']['months'][$month['key']][$key]) }}</td>
                                    @endforeach
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-middle font-numeric-data font-bold {{ $background }}">{{ $formatAmount($report['recap']['totals'][$key]) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
