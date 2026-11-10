<div class="annur-report-page min-w-0 space-y-stack-lg">
    @php
        $formatAmount = static fn (float|int $amount): string => 'Rp '.number_format((float) $amount, 0, ',', '.');
        $statusLabels = [
            \App\Services\JemputanDriverRecapService::STATUS_PAID => 'Lunas',
            \App\Services\JemputanDriverRecapService::STATUS_PARTIAL => 'Sebagian',
            \App\Services\JemputanDriverRecapService::STATUS_UNPAID => 'Belum Dibayar',
            \App\Services\JemputanDriverRecapService::STATUS_NO_BILL => 'Tidak Ada Tagihan',
            \App\Services\JemputanDriverRecapService::STATUS_NO_ASSIGNMENT => 'Tidak Ditugaskan',
        ];
        $statusClasses = [
            \App\Services\JemputanDriverRecapService::STATUS_PAID => 'bg-primary-fixed/40 text-on-surface',
            \App\Services\JemputanDriverRecapService::STATUS_PARTIAL => 'bg-tertiary-fixed/40 text-on-surface',
            \App\Services\JemputanDriverRecapService::STATUS_UNPAID => 'bg-error-container/30 text-on-surface',
            \App\Services\JemputanDriverRecapService::STATUS_NO_BILL => 'text-on-surface-variant',
            \App\Services\JemputanDriverRecapService::STATUS_NO_ASSIGNMENT => 'bg-surface-container-low text-on-surface-variant',
        ];
    @endphp

    <header>
        <p class="text-label-md font-label-md uppercase tracking-[0.16em] text-primary">Jemputan</p>
        <h1 class="mt-1 text-display-sm font-display-sm text-on-surface">Rekap Jemputan</h1>
        <p class="mt-1 text-body-md text-on-surface-variant">Rekap pembayaran Jemputan siswa berdasarkan kelompok sopir.</p>
    </header>

    <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="min-w-0">
                <label for="jemputan-recap-year" class="block text-body-sm text-on-surface-variant">Tahun Ajaran</label>
                <select id="jemputan-recap-year" wire:model.live="selectedAcademicYearId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Pilih Tahun Ajaran</option>
                    @foreach($academicYears as $academicYear)
                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                    @endforeach
                </select>
                @error('selectedAcademicYearId') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="jemputan-recap-driver" class="block text-body-sm text-on-surface-variant">Sopir</label>
                <select id="jemputan-recap-driver" wire:model.live="selectedDriverId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Pilih Sopir</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}{{ $driver->is_active ? '' : ' (Nonaktif)' }}</option>
                    @endforeach
                </select>
                @error('selectedDriverId') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="jemputan-recap-level" class="block text-body-sm text-on-surface-variant">Jenjang</label>
                <select id="jemputan-recap-level" wire:model.live="selectedSchoolLevel" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua Jenjang</option>
                    @foreach($schoolLevels as $schoolLevel)
                        <option value="{{ $schoolLevel->value }}">{{ $schoolLevel->value }}</option>
                    @endforeach
                </select>
                @error('selectedSchoolLevel') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="jemputan-recap-class" class="block text-body-sm text-on-surface-variant">Kelas</label>
                <select id="jemputan-recap-class" wire:model.live="selectedSchoolClassId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua Kelas</option>
                    @foreach($classes as $schoolClass)
                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                    @endforeach
                </select>
                @error('selectedSchoolClassId') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="jemputan-recap-per-page" class="block text-body-sm text-on-surface-variant">Baris per Halaman</label>
                <select id="jemputan-recap-per-page" wire:model.live="perPage" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    @foreach([10, 25, 50] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
                @error('perPage') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex flex-col gap-3 border-t border-outline-variant pt-4 sm:flex-row sm:items-end sm:justify-end">
            <div class="w-full sm:w-64">
                <label for="jemputan-export-mode" class="block text-body-sm text-on-surface-variant">Export Excel</label>
                <select id="jemputan-export-mode" wire:model.live="exportMode" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="all">Semua Sopir</option>
                    <option value="filtered">Berdasarkan Filter</option>
                </select>
                @error('exportMode') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                <span class="material-symbols-outlined text-[20px]">download</span>
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Menyiapkan...</span>
            </button>
        </div>
        @error('export') <p class="mt-3 text-right text-body-sm text-error">{{ $message }}</p> @enderror
    </section>

    @if($selectedDriverId === '')
        <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
            <span class="material-symbols-outlined text-5xl text-on-surface-variant">airport_shuttle</span>
            <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Pilih sopir untuk menampilkan rekap.</p>
            <p class="mt-1 text-body-md text-on-surface-variant">Data pembayaran tetap dibaca dari tagihan Jemputan dan alokasi pembayaran yang tersimpan.</p>
        </section>
    @elseif($report !== null)
        <section aria-label="Ringkasan halaman ini" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach([
                ['Total Tagihan', $formatAmount($report['summary']['overall']['effective_billed_amount']), 'receipt_long'],
                ['Total Dibayar', $formatAmount($report['summary']['overall']['paid_amount']), 'payments'],
                ['Total Sisa', $formatAmount($report['summary']['overall']['remaining_amount']), 'pending_actions'],
                ['Jumlah Siswa', count($report['rows']).' siswa', 'groups'],
            ] as [$label, $value, $icon])
                <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-on-surface-variant">
                        <span class="material-symbols-outlined text-[20px]">{{ $icon }}</span>
                        <p class="truncate text-label-md font-label-md">{{ $label }}</p>
                    </div>
                    <p class="mt-2 whitespace-nowrap text-title-lg font-title-lg text-on-surface">{{ $value }}</p>
                    <p class="mt-1 text-label-sm text-on-surface-variant">Halaman Ini</p>
                </article>
            @endforeach
        </section>

        @if($report['rows'] === [])
            <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">group_off</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada siswa pada filter ini.</p>
            </section>
        @else
            <section class="hidden min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm md:block">
                <header class="flex items-center justify-between gap-4 border-b border-outline-variant px-5 py-4 sm:px-6">
                    <div class="min-w-0">
                        <h2 class="truncate text-title-md font-title-md text-on-surface">{{ $report['driver']['name'] }}</h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Tahun Ajaran {{ $report['academic_year']['label'] }}. Pembayaran mengikuti periode tagihan.</p>
                    </div>
                    <p class="shrink-0 text-body-sm text-on-surface-variant">{{ $report['pagination']['from'] }}–{{ $report['pagination']['to'] }} dari {{ $report['pagination']['total'] }}</p>
                </header>

                <div class="rounded-b-xl" x-data="classRecapStickyHeader()">
                    <div class="sticky top-16 z-30 bg-surface-container-low">
                        <div class="overflow-x-hidden" x-ref="cloneScroller">
                            <table class="recap-clone-table min-w-[5600px] border-collapse text-left text-body-sm">
                                <thead class="uppercase tracking-wider text-on-surface-variant">
                                    @include('livewire.partials.jemputan-driver-recap-thead', ['report' => $report])
                                </thead>
                            </table>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-b-xl">
                        <div class="overflow-x-auto overflow-y-hidden overscroll-x-contain rounded-b-xl" x-ref="recapScroller">
                            <table class="recap-real-table min-w-[5600px] border-collapse text-left text-body-sm" x-ref="recapTable">
                                <thead class="recap-measure-head invisible uppercase tracking-wider text-on-surface-variant" aria-hidden="true">
                                    @include('livewire.partials.jemputan-driver-recap-thead', ['report' => $report])
                                </thead>
                                <tbody class="divide-y divide-outline-variant text-on-surface">
                                    @foreach($report['rows'] as $index => $row)
                                        <tr class="h-12 hover:bg-surface-container-low/50">
                                            <td class="sticky left-0 z-20 w-14 min-w-14 whitespace-nowrap border-r border-outline-variant bg-surface-container-lowest px-3 py-2.5 text-center font-numeric-data">{{ (($report['pagination']['current_page'] - 1) * $report['pagination']['per_page']) + $index + 1 }}</td>
                                            <td class="sticky left-14 z-20 w-80 min-w-80 whitespace-nowrap border-r-2 border-outline bg-surface-container-lowest px-4 py-2.5 font-medium">{{ $row['student_name'] }}</td>
                                            <td class="min-w-40 whitespace-nowrap border-r border-outline-variant px-4 py-2.5">{{ $row['historical_class'] ?? '—' }}</td>
                                            <td class="min-w-40 whitespace-nowrap border-r-2 border-outline px-4 py-2.5 text-center font-numeric-data">
                                                @if($row['tariff_varies'])
                                                    <span title="Tarif efektif berbeda antarbulan">Bervariasi</span>
                                                @elseif($row['tariff_amount'] !== null)
                                                    {{ $formatAmount($row['tariff_amount']) }}
                                                @else
                                                    <span class="text-on-surface-variant">—</span>
                                                @endif
                                            </td>
                                            @foreach($report['months'] as $month)
                                                @php
                                                    $monthData = $row['months'][$month['key']];
                                                    $statusLabel = $statusLabels[$monthData['status']];
                                                    $detailTitle = $statusLabel.' · Tagihan efektif '.$formatAmount($monthData['effective_billed_amount']).' · Dibayar '.$formatAmount($monthData['paid_amount']).' · Sisa '.$formatAmount($monthData['remaining_amount']);
                                                @endphp
                                                <td title="{{ $detailTitle }}" class="min-w-36 whitespace-nowrap border-r border-outline-variant px-3 py-2.5 text-right font-numeric-data {{ $statusClasses[$monthData['status']] }}">{{ $monthData['paid_amount'] > 0 ? $formatAmount($monthData['paid_amount']) : '—' }}</td>
                                                <td title="{{ $statusLabel }}" class="min-w-32 whitespace-nowrap border-r border-outline-variant px-3 py-2.5 text-center font-numeric-data">{{ $monthData['payment_date'] === null ? '—' : \Carbon\CarbonImmutable::parse($monthData['payment_date'])->format('d/m/Y') }}</td>
                                                <td title="{{ $statusLabel }}" class="min-w-36 whitespace-nowrap border-r-2 border-outline px-3 py-2.5 text-center">{{ $monthData['bank_display'] ?? '—' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3 md:hidden">
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                    <label for="jemputan-mobile-month" class="block text-body-sm text-on-surface-variant">Bulan yang Ditampilkan</label>
                    <select id="jemputan-mobile-month" wire:model.live="selectedMobileMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @foreach($report['months'] as $month)
                            <option value="{{ $month['key'] }}">{{ $month['label'] }} {{ $month['year'] }}</option>
                        @endforeach
                    </select>
                </div>

                @foreach($report['rows'] as $row)
                    @php
                        $mobileMonth = $row['months'][$selectedMobileMonth] ?? reset($row['months']);
                        $mobileStatus = $statusLabels[$mobileMonth['status']];
                    @endphp
                    <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                        <h2 class="overflow-x-auto whitespace-nowrap text-title-md font-title-md text-on-surface">{{ $row['student_name'] }}</h2>
                        <p class="mt-1 whitespace-nowrap text-body-sm text-on-surface-variant">{{ $row['historical_class'] ?? 'Tanpa kelas' }}</p>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-body-sm">
                            <div>
                                <dt class="text-on-surface-variant">Tarif</dt>
                                <dd class="mt-0.5 whitespace-nowrap font-numeric-data text-on-surface">{{ $row['tariff_varies'] ? 'Bervariasi' : ($row['tariff_amount'] === null ? '—' : $formatAmount($row['tariff_amount'])) }}</dd>
                            </div>
                            <div>
                                <dt class="text-on-surface-variant">Status</dt>
                                <dd class="mt-0.5 whitespace-nowrap text-on-surface">{{ $mobileStatus }}</dd>
                            </div>
                            <div>
                                <dt class="text-on-surface-variant">Nominal Dibayar</dt>
                                <dd class="mt-0.5 whitespace-nowrap font-numeric-data text-on-surface">{{ $mobileMonth['paid_amount'] > 0 ? $formatAmount($mobileMonth['paid_amount']) : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-on-surface-variant">Tanggal Bayar</dt>
                                <dd class="mt-0.5 whitespace-nowrap font-numeric-data text-on-surface">{{ $mobileMonth['payment_date'] === null ? '—' : \Carbon\CarbonImmutable::parse($mobileMonth['payment_date'])->format('d/m/Y') }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-on-surface-variant">Bank</dt>
                                <dd class="mt-0.5 whitespace-nowrap text-on-surface">{{ $mobileMonth['bank_display'] ?? '—' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </section>

            @if($report['pagination']['last_page'] > 1)
                <nav aria-label="Paginasi rekap Jemputan" class="flex items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 shadow-sm">
                    <button type="button" wire:click="previousPage('recapPage')" @disabled($report['pagination']['current_page'] <= 1) class="inline-flex h-10 items-center gap-1 rounded-lg border border-outline-variant px-3 text-label-md font-label-md text-on-surface disabled:cursor-not-allowed disabled:opacity-40">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        Sebelumnya
                    </button>
                    <p class="whitespace-nowrap text-body-sm text-on-surface-variant">Halaman {{ $report['pagination']['current_page'] }} dari {{ $report['pagination']['last_page'] }}</p>
                    <button type="button" wire:click="nextPage('recapPage')" @disabled($report['pagination']['current_page'] >= $report['pagination']['last_page']) class="inline-flex h-10 items-center gap-1 rounded-lg border border-outline-variant px-3 text-label-md font-label-md text-on-surface disabled:cursor-not-allowed disabled:opacity-40">
                        Berikutnya
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                </nav>
            @endif
        @endif
    @endif
</div>
