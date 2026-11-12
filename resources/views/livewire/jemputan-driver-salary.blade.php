<div class="annur-report-page min-w-0 space-y-stack-lg">
    @php
        $formatAmount = static fn (float|int|null $amount): string => $amount === null
            ? '—'
            : 'Rp '.number_format((float) $amount, 0, ',', '.');
        $formatPercentage = static fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $paymentBadgeClasses = static fn (string $status): string => match ($status) {
            \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_PAID => 'bg-tertiary-fixed text-on-tertiary-fixed',
            \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_PARTIAL => 'bg-surface-container-highest text-on-surface-variant',
            \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_UNPAID => 'bg-error-container text-on-error-container',
            default => 'bg-surface-container-high text-on-surface-variant',
        };
    @endphp

    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-label-md font-label-md uppercase tracking-[0.16em] text-primary">Jemputan</p>
            <h1 class="mt-1 text-display-sm font-display-sm text-on-surface">Gaji Sopir</h1>
            <p class="mt-1 text-body-md text-on-surface-variant">Rekap pendapatan Jemputan dan potongan yayasan.</p>
        </div>
        <div class="flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
            <button
                type="button"
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                @disabled($report === null)
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-primary bg-primary px-5 text-label-lg font-label-lg text-on-primary shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:border-outline-variant disabled:bg-surface-container-high disabled:text-on-surface-variant disabled:opacity-70"
            >
                <span class="material-symbols-outlined text-[20px]">download</span>
                Export Excel
            </button>
            @error('export') <p class="max-w-xs text-body-sm text-error">{{ $message }}</p> @enderror
        </div>
    </header>

    <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="min-w-0">
                <label for="jemputan-salary-year" class="block text-body-sm text-on-surface-variant">Tahun Ajaran</label>
                <select id="jemputan-salary-year" wire:model.live="selectedAcademicYearId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Pilih Tahun Ajaran</option>
                    @foreach($academicYears as $academicYear)
                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                    @endforeach
                </select>
                @error('selectedAcademicYearId') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="jemputan-salary-driver" class="block text-body-sm text-on-surface-variant">Sopir</label>
                <select id="jemputan-salary-driver" wire:model.live="selectedDriverId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Pilih Sopir</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}{{ $driver->is_active ? '' : ' (Nonaktif)' }}</option>
                    @endforeach
                </select>
                @error('selectedDriverId') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="jemputan-salary-month" class="block text-body-sm text-on-surface-variant">Bulan</label>
                <select id="jemputan-salary-month" wire:model.live="selectedMonth" @disabled($report === null) class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-surface-container-high disabled:text-on-surface-variant">
                    @if($report === null)
                        <option value="">Pilih sopir terlebih dahulu</option>
                    @else
                        @foreach($report['months'] as $month)
                            <option value="{{ $month['key'] }}">{{ $month['label'] }}</option>
                        @endforeach
                    @endif
                </select>
                @error('selectedMonth') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    @if($selectedDriverId === '')
        <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
            <span class="material-symbols-outlined text-5xl text-on-surface-variant">airport_shuttle</span>
            <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Pilih sopir untuk menghitung gaji.</p>
            <p class="mt-1 text-body-md text-on-surface-variant">Pendapatan dihitung dari nominal efektif tagihan Jemputan pada bulan yang dipilih.</p>
        </section>
    @elseif($report !== null)
        <section aria-label="Keterangan perhitungan gaji" class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
            <p class="text-body-sm leading-relaxed text-on-surface-variant">Gaji sopir dihitung berdasarkan seluruh tagihan Jemputan, termasuk siswa yang belum membayar. Tagihan yang belum dibayar ditanggung sementara oleh sekolah dan tetap masuk perhitungan gaji.</p>
        </section>

        @if(! $report['summary']['percentage_configured'])
            <section class="rounded-xl border border-error/30 bg-error-container/20 px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[22px] text-error">warning</span>
                    <div class="min-w-0">
                        <p class="text-label-lg font-label-lg text-on-surface">Persentase potongan yayasan belum diatur.</p>
                        <p class="mt-1 text-body-sm text-on-surface-variant">
                            Potongan dan gaji bersih belum dapat dihitung final. Atur persentase sopir terlebih dahulu.
                        </p>
                        <a href="{{ route('jemputan.drivers.index') }}" class="mt-2 inline-flex items-center gap-1 text-label-md font-label-md text-primary hover:underline">
                            <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                            Buka Master Sopir
                        </a>
                    </div>
                </div>
            </section>
        @endif

        @if($report['has_multi_driver'])
            <section class="rounded-xl border {{ $report['summary']['has_allocation_warning'] ? 'border-tertiary/30 bg-tertiary-fixed/20' : 'border-primary/20 bg-primary-container/30' }} px-5 py-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[22px] {{ $report['summary']['has_allocation_warning'] ? 'text-tertiary' : 'text-primary' }}">{{ $report['summary']['has_allocation_warning'] ? 'warning' : 'check_circle' }}</span>
                    <div class="min-w-0">
                        <p class="text-label-lg font-label-lg text-on-surface">Terdapat {{ $report['multi_driver_student_count'] }} siswa Multi-Sopir.</p>
                        @if($report['summary']['has_allocation_warning'])
                            <p class="mt-1 text-body-sm text-on-surface-variant">
                                {{ $report['summary']['unallocated_multi_driver_count'] }} siswa belum dialokasikan dan {{ $report['summary']['stale_allocation_count'] }} siswa perlu diperbaiki. Total pendapatan dan gaji bersih di halaman ini masih simulasi berdasarkan nominal tagihan penuh, bukan angka final. Gunakan tombol Atur Pembagian pada baris siswa.
                            </p>
                        @else
                            <p class="mt-1 text-body-sm text-on-surface-variant">
                                Seluruh siswa Multi-Sopir sudah memiliki pembagian yang valid. Gaji sopir menggunakan nominal alokasi yang tersimpan; nominal tagihan siswa tidak berubah.
                            </p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section aria-label="Ringkasan gaji sopir" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                <div class="flex items-center gap-2 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    <p class="truncate text-label-md font-label-md">Total Pendapatan</p>
                </div>
                <p class="mt-2 whitespace-nowrap text-title-lg font-title-lg text-on-surface">{{ $formatAmount($report['summary']['revenue']) }}</p>
                <p class="mt-1 text-label-sm text-on-surface-variant">Seluruh siswa sesuai filter</p>
            </article>

            <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                <div class="flex items-center gap-2 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[20px]">percent</span>
                    <p class="truncate text-label-md font-label-md">Potongan Yayasan</p>
                </div>
                @if($report['summary']['percentage_configured'])
                    <p class="mt-2 whitespace-nowrap text-title-lg font-title-lg text-on-surface">{{ $formatAmount($report['summary']['deduction']) }}</p>
                    <p class="mt-1 text-label-sm text-on-surface-variant">{{ $formatPercentage($report['summary']['percentage']) }}% dari pendapatan</p>
                @else
                    <p class="mt-2 whitespace-nowrap text-title-lg font-title-lg text-error">Belum diatur</p>
                    <p class="mt-1 text-label-sm text-on-surface-variant">Atur persentase di Master Sopir</p>
                @endif
            </article>

            <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                <div class="flex items-center gap-2 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                    <p class="truncate text-label-md font-label-md">Total Diterima Sopir</p>
                </div>
                @if($report['summary']['percentage_configured'])
                    <p class="mt-2 whitespace-nowrap text-title-lg font-title-lg text-on-surface">{{ $formatAmount($report['summary']['net_salary']) }}</p>
                    <p class="mt-1 text-label-sm text-on-surface-variant">Setelah potongan yayasan</p>
                @else
                    <p class="mt-2 whitespace-nowrap text-title-lg font-title-lg text-on-surface-variant">Belum final</p>
                    <p class="mt-1 text-label-sm text-on-surface-variant">Menunggu persentase sopir</p>
                @endif
            </article>
        </section>

        @if($report['rows'] === [])
            <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">group_off</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada siswa pada filter ini.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Siswa tanpa tagihan pada bulan yang dipilih tetap dihitung Rp 0.</p>
            </section>
        @else
            <section class="hidden min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm md:block">
                <header class="flex flex-col gap-2 border-b border-outline-variant px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 class="truncate text-title-md font-title-md text-on-surface">{{ $report['driver']['name'] }}</h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Tahun Ajaran {{ $report['academic_year']['label'] }} · {{ $report['month']['label'] }}</p>
                    </div>
                    <p class="shrink-0 text-body-sm text-on-surface-variant">{{ $report['pagination']['from'] }}–{{ $report['pagination']['to'] }} dari {{ $report['pagination']['total'] }} siswa</p>
                </header>

                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse text-left text-body-sm">
                        <thead class="bg-surface-container-low uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="w-16 px-4 py-3 text-center font-label-md">No</th>
                                <th class="min-w-64 px-4 py-3 font-label-md">Nama Siswa</th>
                                <th class="min-w-32 px-4 py-3 font-label-md">Kelas</th>
                                <th data-column="bill-amount" class="min-w-40 px-4 py-3 text-right font-label-md">Tagihan</th>
                                <th data-column="payment-status" class="min-w-44 px-4 py-3 text-center font-label-md">Status Tagihan</th>
                                <th data-column="description" class="min-w-[42rem] px-4 py-3 font-label-md">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-on-surface">
                            @foreach($report['rows'] as $index => $row)
                                <tr class="hover:bg-surface-container-low/50">
                                    <td class="whitespace-nowrap px-4 py-3 text-center align-middle font-numeric-data">{{ (($report['pagination']['current_page'] - 1) * $report['pagination']['per_page']) + $index + 1 }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle font-medium">{{ $row['student_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 align-middle">{{ $row['historical_class'] ?? '—' }}</td>
                                    <td data-column="bill-amount" class="whitespace-nowrap px-4 py-3 text-right align-middle font-numeric-data">
                                        {{ $formatAmount($row['bill_amount']) }}
                                    </td>
                                    <td data-column="payment-status" class="whitespace-nowrap px-4 py-3 text-center align-middle">
                                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $paymentBadgeClasses($row['payment_status']) }}">
                                            @if($row['payment_status'] === \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
                                                <span class="material-symbols-outlined text-[14px] leading-none">check_circle</span>
                                            @elseif($row['payment_status'] === \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_PARTIAL)
                                                <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                            @elseif($row['payment_status'] === \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_UNPAID)
                                                <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                            @endif
                                            {{ $row['payment_status_label'] }}
                                        </span>
                                    </td>
                                    <td data-column="description" class="whitespace-nowrap px-4 py-3 align-middle">
                                        @if($row['is_multi_driver'])
                                            <div class="flex items-center gap-2 whitespace-nowrap">
                                                @if(! $row['has_bill'])
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 text-label-sm font-label-sm text-on-surface-variant">
                                                        <span class="material-symbols-outlined text-[16px]">groups</span>
                                                        Multi-Sopir
                                                    </span>
                                                @elseif($row['allocation_status'] === 'valid')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-container px-2.5 py-1 text-label-sm font-label-sm text-on-primary-container">
                                                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                                        Multi-Sopir — Alokasi Valid
                                                    </span>
                                                    <span class="text-label-sm text-on-surface-variant">Gaji sopir: <span class="font-numeric-data text-on-surface">{{ $formatAmount($row['salary_amount']) }}</span></span>
                                                @elseif($row['allocation_status'] === 'missing')
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-tertiary-fixed px-2.5 py-1 text-label-sm font-label-sm text-on-surface">
                                                        <span class="material-symbols-outlined text-[16px]">groups</span>
                                                        Multi-Sopir — Belum Dialokasikan
                                                    </span>
                                                    <span class="text-label-sm text-error">Angka belum final · sementara {{ $formatAmount($row['salary_amount']) }}</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-error-container px-2.5 py-1 text-label-sm font-label-sm text-on-error-container">
                                                        <span class="material-symbols-outlined text-[16px]">error</span>
                                                        Multi-Sopir — Perlu Diperbaiki
                                                    </span>
                                                    <span class="text-label-sm text-error">Angka belum final · sementara {{ $formatAmount($row['salary_amount']) }}</span>
                                                @endif
                                                @if($row['has_bill'])
                                                    <span class="text-label-sm text-on-surface-variant">{{ $row['current_driver_count'] }} sopir aktif</span>
                                                    <button
                                                        type="button"
                                                        wire:click="openAllocationModal({{ $row['student_id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="openAllocationModal({{ $row['student_id'] }})"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-primary/40 px-3 py-1.5 text-label-md font-label-md text-primary transition hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        <span class="material-symbols-outlined text-[17px]">call_split</span>
                                                        Atur Pembagian
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="inline-flex items-center gap-2 whitespace-nowrap text-on-surface-variant">
                                                <span>Sopir Tunggal</span>
                                                <span aria-hidden="true">·</span>
                                                <span class="text-label-sm">Gaji sopir: <span class="font-numeric-data text-on-surface">{{ $formatAmount($row['salary_amount']) }}</span></span>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3 md:hidden">
                @foreach($report['rows'] as $index => $row)
                    <article class="min-w-0 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="overflow-x-auto whitespace-nowrap text-title-md font-title-md text-on-surface">{{ $row['student_name'] }}</h2>
                                <p class="mt-1 whitespace-nowrap text-body-sm text-on-surface-variant">{{ $row['historical_class'] ?? 'Tanpa kelas' }}</p>
                            </div>
                            <span class="shrink-0 font-numeric-data text-label-md text-on-surface-variant">{{ (($report['pagination']['current_page'] - 1) * $report['pagination']['per_page']) + $index + 1 }}</span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-body-sm">
                            <div>
                                <dt class="text-on-surface-variant">Tagihan</dt>
                                <dd class="mt-1 flex flex-wrap items-center gap-2 font-numeric-data text-on-surface">
                                    <span class="whitespace-nowrap">{{ $formatAmount($row['bill_amount']) }}</span>
                                    <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $paymentBadgeClasses($row['payment_status']) }}">
                                        @if($row['payment_status'] === \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_PAID)
                                            <span class="material-symbols-outlined text-[14px] leading-none">check_circle</span>
                                        @elseif($row['payment_status'] === \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_PARTIAL)
                                            <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                        @elseif($row['payment_status'] === \App\Services\JemputanDriverSalaryService::PAYMENT_STATUS_UNPAID)
                                            <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                        @endif
                                        {{ $row['payment_status_label'] }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-on-surface-variant">Gaji Sopir</dt>
                                <dd class="mt-0.5 whitespace-nowrap font-numeric-data text-on-surface">{{ $formatAmount($row['salary_amount']) }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-on-surface-variant">Status Multi-Sopir</dt>
                                <dd class="mt-0.5 text-on-surface">
                                    @if($row['is_multi_driver'])
                                        @if(! $row['has_bill'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 text-label-sm font-label-sm text-on-surface-variant">
                                                <span class="material-symbols-outlined text-[16px]">groups</span>
                                                Multi-Sopir
                                            </span>
                                        @elseif($row['allocation_status'] === 'valid')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-primary-container px-2.5 py-1 text-label-sm font-label-sm text-on-primary-container">
                                                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                                Multi-Sopir — Alokasi Valid
                                            </span>
                                        @elseif($row['allocation_status'] === 'missing')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-tertiary-fixed px-2.5 py-1 text-label-sm font-label-sm text-on-surface">
                                                <span class="material-symbols-outlined text-[16px]">groups</span>
                                                Multi-Sopir — Belum Dialokasikan
                                            </span>
                                            <span class="mt-1 block text-label-sm text-error">Angka belum final.</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-error-container px-2.5 py-1 text-label-sm font-label-sm text-on-error-container">
                                                <span class="material-symbols-outlined text-[16px]">error</span>
                                                Multi-Sopir — Perlu Diperbaiki
                                            </span>
                                            <span class="mt-1 block text-label-sm text-error">Angka belum final.</span>
                                        @endif
                                        @if($row['has_bill'])
                                            <span class="mt-1 block text-label-sm text-on-surface-variant">{{ $row['current_driver_count'] }} sopir aktif</span>
                                        @endif
                                    @else
                                        <span>Sopir Tunggal</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        @if($row['is_multi_driver'] && $row['has_bill'])
                            <button
                                type="button"
                                wire:click="openAllocationModal({{ $row['student_id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="openAllocationModal({{ $row['student_id'] }})"
                                class="mt-4 inline-flex min-h-10 w-full items-center justify-center gap-1 rounded-lg border border-primary/40 px-3 py-2 text-label-md font-label-md text-primary transition hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span class="material-symbols-outlined text-[18px]">call_split</span>
                                Atur Pembagian
                            </button>
                        @endif
                    </article>
                @endforeach
            </section>

            @if($report['pagination']['last_page'] > 1)
                <nav aria-label="Paginasi gaji sopir" class="flex items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 shadow-sm">
                    <button type="button" wire:click="previousPage('salaryPage')" @disabled($report['pagination']['current_page'] <= 1) class="inline-flex h-10 items-center gap-1 rounded-lg border border-outline-variant px-3 text-label-md font-label-md text-on-surface disabled:cursor-not-allowed disabled:opacity-40">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                        Sebelumnya
                    </button>
                    <p class="whitespace-nowrap text-body-sm text-on-surface-variant">Halaman {{ $report['pagination']['current_page'] }} dari {{ $report['pagination']['last_page'] }}</p>
                    <button type="button" wire:click="nextPage('salaryPage')" @disabled($report['pagination']['current_page'] >= $report['pagination']['last_page']) class="inline-flex h-10 items-center gap-1 rounded-lg border border-outline-variant px-3 text-label-md font-label-md text-on-surface disabled:cursor-not-allowed disabled:opacity-40">
                        Berikutnya
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                </nav>
            @endif
        @endif
    @endif

    @if($isAllocationModalOpen)
        @php
            $allocationBillAmount = (float) ($allocationPreview['bill_amount'] ?? 0);
            $allocationIsMatch = abs($allocationDifference) < 0.005;
            $allocationIsLess = $allocationDifference > 0.005;
            $allocationIsMore = $allocationDifference < -0.005;
        @endphp
        <template x-teleport="body">
            <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="closeAllocationModal">
                <div class="flex min-h-full items-center justify-center">
                    <div class="relative w-full max-w-2xl rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-label-md font-label-md uppercase tracking-[0.14em] text-primary">Atur Pembagian</p>
                                <h2 class="mt-1 text-title-lg font-title-lg text-on-surface">Gaji Sopir Multi-Sopir</h2>
                            </div>
                            <button type="button" wire:click="closeAllocationModal" class="shrink-0 rounded-lg p-2 text-on-surface-variant hover:bg-surface-container" aria-label="Tutup modal">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        <div class="mt-4 rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                            <p class="truncate text-body-lg font-label-lg text-on-surface">{{ $allocationPreview['student_name'] ?? '—' }}</p>
                            <p class="mt-0.5 text-body-sm text-on-surface-variant">
                                {{ $allocationStudentNis ?: 'NIS —' }} · {{ $allocationStudentClass ?? 'Kelas —' }} · {{ $allocationPreview['period']['label'] ?? '—' }}
                            </p>
                        </div>

                        <div class="mt-4 flex flex-col gap-1 rounded-xl border border-primary/20 bg-primary-container/30 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-label-md text-on-surface">Tagihan Jemputan</span>
                            <span class="font-numeric-data text-title-md font-title-md text-on-surface">{{ $formatAmount($allocationBillAmount) }}</span>
                        </div>

                        <p class="mt-3 text-body-sm text-on-surface-variant">
                            Pembagian ini hanya untuk perhitungan gaji sopir. Nominal tagihan siswa tidak berubah.
                        </p>

                        @if(! ($allocationPreview['has_bill'] ?? false))
                            <p class="mt-3 rounded-lg border border-error/30 bg-error-container/30 px-3 py-2 text-body-sm text-on-error-container">
                                Tagihan Jemputan untuk periode ini belum tersedia. Pembagian tidak dapat disimpan.
                            </p>
                        @endif

                        <div class="mt-4 space-y-3">
                            @foreach(($allocationPreview['drivers'] ?? []) as $driver)
                                <div class="rounded-xl border border-outline-variant p-3.5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <label for="allocation-driver-{{ $driver['id'] }}" class="block truncate text-label-lg font-label-lg text-on-surface">{{ $driver['name'] }}</label>
                                            <p class="mt-0.5 text-label-sm text-on-surface-variant">{{ $driver['is_active'] ? 'Aktif' : 'Nonaktif' }} · anggota saat ini</p>
                                        </div>
                                        <span class="shrink-0 text-label-sm text-on-surface-variant">Rp</span>
                                    </div>
                                    <input
                                        id="allocation-driver-{{ $driver['id'] }}"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        wire:model.live.debounce.400ms="allocationAmounts.{{ $driver['id'] }}"
                                        class="mt-2 h-11 w-full rounded-lg border-outline-variant text-right font-numeric-data focus:border-primary focus:ring-primary"
                                        placeholder="0"
                                    >
                                    @error('allocationAmounts.'.$driver['id'])
                                        <p class="mt-1 text-body-sm text-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <p class="text-label-sm text-on-surface-variant">Total Alokasi</p>
                                <p class="mt-1 font-numeric-data text-title-md font-title-md text-on-surface">{{ $formatAmount($allocationTotal) }}</p>
                            </div>
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <p class="text-label-sm text-on-surface-variant">Selisih</p>
                                <p class="mt-1 font-numeric-data text-title-md font-title-md {{ $allocationIsMatch ? 'text-primary' : 'text-error' }}">{{ $formatAmount($allocationDifference) }}</p>
                            </div>
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <p class="text-label-sm text-on-surface-variant">Status</p>
                                @if($allocationIsMatch)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary-container px-2.5 py-1 text-label-sm font-label-sm text-on-primary-container">
                                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                        Cocok
                                    </span>
                                @elseif($allocationIsLess)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-tertiary-fixed px-2.5 py-1 text-label-sm font-label-sm text-on-surface">
                                        <span class="material-symbols-outlined text-[16px]">trending_down</span>
                                        Kurang
                                    </span>
                                @elseif($allocationIsMore)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-error-container px-2.5 py-1 text-label-sm font-label-sm text-on-error-container">
                                        <span class="material-symbols-outlined text-[16px]">trending_up</span>
                                        Lebih
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-outline-variant px-4 py-3">
                            <label class="flex items-start gap-3 text-body-md text-on-surface">
                                <input type="checkbox" wire:model.live="applyAllocationToOtherMonths" class="mt-0.5 rounded border-outline-variant text-primary focus:ring-primary">
                                <span>
                                    <span class="block font-label-md text-on-surface">Terapkan juga ke bulan lain</span>
                                    <span class="mt-0.5 block text-body-sm text-on-surface-variant">Nominal yang sama hanya dapat disimpan ke bulan dengan tagihan yang jumlahnya sama.</span>
                                </span>
                            </label>

                            @if($applyAllocationToOtherMonths)
                                <div class="mt-3 space-y-2 border-t border-outline-variant pt-3">
                                    @forelse($allocationMonthOptions as $monthOption)
                                        <label class="flex items-center gap-3 text-body-sm text-on-surface">
                                            <input
                                                type="checkbox"
                                                value="{{ $monthOption['key'] }}"
                                                wire:model.live="additionalAllocationMonthKeys"
                                                class="rounded border-outline-variant text-primary focus:ring-primary"
                                            >
                                            {{ $monthOption['label'] }}
                                        </label>
                                    @empty
                                        <p class="text-body-sm text-on-surface-variant">Tidak ada bulan lain dengan tagihan Jemputan pada tahun ajaran ini.</p>
                                    @endforelse
                                    @error('allocationMonths')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                                </div>
                            @endif
                        </div>

                        @error('allocationForm')
                            <p class="mt-3 rounded-lg border border-error/30 bg-error-container/30 px-3 py-2 text-body-sm text-on-error-container">{{ $message }}</p>
                        @enderror

                        <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" wire:click="closeAllocationModal" class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                            <button
                                type="button"
                                wire:click="saveAllocation"
                                wire:loading.attr="disabled"
                                wire:target="saveAllocation"
                                @disabled(! ($allocationPreview['has_bill'] ?? false) || ($allocationPreview['drivers'] ?? []) === [])
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary transition hover:opacity-90 disabled:cursor-not-allowed disabled:bg-surface-container-high disabled:text-on-surface-variant"
                            >
                                <span class="material-symbols-outlined text-[18px]">save</span>
                                Simpan Pembagian
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
