<div class="annur-report-page space-y-stack-lg">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-label-md font-label-md uppercase tracking-[0.16em] text-primary">Keuangan Sekolah</p>
            <h1 class="mt-1 text-display-sm font-display-sm text-on-surface">{{ $activeTab === 'class' ? 'Rekap Per Kelas' : ($activeTab === 'target' ? 'Target & Tunggakan' : ($activeTab === 'monthly' ? 'Laporan Bulanan' : 'Laporan Penerimaan')) }}</h1>
            <p class="mt-1 text-body-md text-on-surface-variant">{{ $activeTab === 'class' ? 'Rekap terpadu tagihan dan pembayaran siswa berdasarkan kelas historis.' : ($activeTab === 'target' ? 'Pantau target, pembayaran, dan sisa tagihan siswa.' : ($activeTab === 'monthly' ? 'Rekap penerimaan siswa selama satu bulan.' : ($activeTab === 'bank' ? 'Rekap pembayaran siswa berdasarkan tanggal penerimaan aktual.' : 'Rekap pembayaran siswa berdasarkan tanggal transaksi dicatat.'))) }}</p>
        </div>

        @if($activeTab === 'daily')
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-row">
                <a
                    href="{{ route('laporan.harian.riwayat.pdf', ['start_date' => $appliedStartDate, 'end_date' => $appliedEndDate, 'school_level' => $this->schoolLevel]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    Cetak Riwayat Transaksi
                </a>
                <a
                    href="{{ route('laporan.harian.export', ['start_date' => $appliedStartDate, 'end_date' => $appliedEndDate, 'school_level' => $this->schoolLevel]) }}"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Unduh Excel
                </a>
                <a
                    href="{{ route('laporan.harian.pdf', ['start_date' => $appliedStartDate, 'end_date' => $appliedEndDate, 'school_level' => $this->schoolLevel]) }}"
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
                @php
                    $monthlyExportRoute = match ($monthlyMode) {
                        'by_level' => 'laporan.jenjang.export',
                        'all_units' => 'laporan.seluruh-unit.export',
                        default => 'laporan.bulanan.export',
                    };
                    $monthlyPdfRoute = match ($monthlyMode) {
                        'by_level' => 'laporan.jenjang.pdf',
                        'all_units' => 'laporan.seluruh-unit.pdf',
                        default => 'laporan.bulanan.pdf',
                    };
                    $monthlyExportParameters = ['month' => $reportMonth, 'year' => $reportYear];
                    if ($monthlyMode === 'by_date') {
                        $monthlyExportParameters['school_level'] = $this->schoolLevel;
                    }
                @endphp
                <a
                    href="{{ route($monthlyExportRoute, $monthlyExportParameters) }}"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Unduh Excel
                </a>
                <a
                    href="{{ route($monthlyPdfRoute, $monthlyExportParameters) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">print</span>
                    Cetak PDF
                </a>
            </div>
        @elseif($activeTab === 'bank')
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-row">
                <a
                    href="{{ route('laporan.bank.export', ['start_date' => $appliedBankStartDate, 'end_date' => $appliedBankEndDate, 'bank' => $bankFilter]) }}"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Unduh Excel
                </a>
                <a
                    href="{{ route('laporan.bank.pdf', ['start_date' => $appliedBankStartDate, 'end_date' => $appliedBankEndDate, 'bank' => $bankFilter]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 sm:w-auto"
                >
                    <span class="material-symbols-outlined text-[20px]">print</span>
                    Cetak PDF
                </a>
            </div>
        @elseif($activeTab === 'target')
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-row">
                <a href="{{ route('laporan.target.export', ['mode' => $targetMode, 'month' => $targetMonth, 'year' => $targetYear, 'academic_year' => $targetAcademicYear, 'school_level' => $this->schoolLevel]) }}" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-5 text-label-lg font-label-lg text-on-surface transition-colors hover:bg-surface-container-low sm:w-auto">
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Unduh Excel
                </a>
                <a href="{{ route('laporan.target.pdf', ['mode' => $targetMode, 'month' => $targetMonth, 'year' => $targetYear, 'academic_year' => $targetAcademicYear, 'school_level' => $this->schoolLevel]) }}" target="_blank" rel="noopener" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-label-lg font-label-lg text-on-primary transition-colors hover:bg-primary/90 sm:w-auto">
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
            <button type="button" wire:click="setActiveTab('bank')" class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'bank' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Rekap Bank
                @if($activeTab === 'bank')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
            <button type="button" wire:click="setActiveTab('target')" class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'target' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Target & Tunggakan
                @if($activeTab === 'target')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
            <button type="button" wire:click="setActiveTab('class')" class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'class' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                Rekap Per Kelas
                @if($activeTab === 'class')
                    <span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>
                @endif
            </button>
        </nav>
    </div>

    @if($activeTab === 'target')
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
            <div class="flex gap-1 overflow-x-auto border-b border-outline-variant" aria-label="Mode target dan tunggakan">
                @foreach(['monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'one_time' => 'Sekali Bayar', 'all' => 'Semua'] as $mode => $label)
                    <button type="button" wire:click="setTargetMode('{{ $mode }}')" class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap {{ $targetMode === $mode ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                        {{ $label }}
                        @if($targetMode === $mode)<span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>@endif
                    </button>
                @endforeach
            </div>
            @php
                $targetFilterColumns = match ($targetMode) {
                    'all' => 'lg:grid-cols-4',
                    'monthly' => 'lg:grid-cols-3',
                    default => 'lg:grid-cols-2',
                };
            @endphp
            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2 {{ $targetFilterColumns }}">
                    @if(in_array($targetMode, ['monthly', 'all'], true))
                        <div class="min-w-0">
                            <label for="target-month" class="block text-body-sm text-on-surface-variant">Bulan</label>
                            <select id="target-month" wire:model.live="targetMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                                @foreach($monthOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label for="target-year" class="block text-body-sm text-on-surface-variant">Tahun</label>
                            <select id="target-year" wire:model.live="targetYear" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                                @foreach($yearOptions as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    @if($targetMode !== 'monthly')
                        <div class="min-w-0">
                            <label for="target-academic-year" class="block text-body-sm text-on-surface-variant">Tahun Ajaran</label>
                            <select id="target-academic-year" wire:model.live="targetAcademicYear" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                                @foreach($academicYearOptions as $academicYear)<option value="{{ $academicYear }}">{{ $academicYear }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <label for="target-level" class="block text-body-sm text-on-surface-variant">Jenjang</label>
                        <select id="target-level" wire:model.live="schoolLevel" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($levelOptions as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
                        </select>
                    </div>
                </div>
            <p class="mt-4 text-body-sm text-on-surface-variant">Periode: <strong class="text-on-surface">{{ $targetReport['period_label'] }}</strong> <span aria-hidden="true">&bull;</span> Unit: <strong class="text-on-surface">{{ $targetReport['unit_name'] }}</strong></p>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['label' => 'TOTAL TARGET', 'value' => 'Rp '.number_format($targetReport['totals']['target'], 0, ',', '.')],
                ['label' => 'SUDAH TERBAYAR', 'value' => 'Rp '.number_format($targetReport['totals']['paid'], 0, ',', '.')],
                ['label' => 'TUNGGAKAN', 'value' => 'Rp '.number_format($targetReport['totals']['outstanding'], 0, ',', '.')],
                ['label' => 'CAPAIAN', 'value' => $targetReport['totals']['achievement_label']],
            ] as $card)
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm">
                    <p class="text-label-sm font-label-sm tracking-wider text-on-surface-variant">{{ $card['label'] }}</p>
                    <p class="mt-3 whitespace-nowrap font-numeric-data text-headline-md font-headline-md text-on-surface">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        @php $renderedTargetSections = $targetReport['sections'] ?? [$targetReport['mode'] => $targetReport]; @endphp
        @foreach($renderedTargetSections as $sectionMode => $sectionReport)
            <section wire:key="target-section-{{ $sectionMode }}" class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                <div class="border-b border-outline-variant px-5 py-4">
                    <h2 class="text-title-md font-title-md text-on-surface">{{ $targetMode === 'all' ? 'TAGIHAN' : 'TARGET' }} {{ strtoupper($sectionReport['mode_label']) }}</h2>
                    <p class="mt-1 text-body-sm text-on-surface-variant">{{ $sectionReport['period_label'] }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] border-collapse text-left">
                        <thead><tr class="border-b border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                            <th class="px-5 py-3">Jenis Tagihan</th><th class="px-5 py-3 text-right">Target</th><th class="px-5 py-3 text-right">Terbayar</th><th class="px-5 py-3 text-right">Tunggakan</th><th class="px-5 py-3 text-right">Capaian</th><th class="px-5 py-3 text-right">Aksi</th>
                        </tr></thead>
                        @forelse($sectionReport['rows'] as $row)
                            <tbody wire:key="target-{{ $sectionMode }}-{{ $row['payment_type_id'] }}" x-data="{ open: false }" class="border-b border-outline-variant last:border-b-0">
                                <tr>
                                    <td class="px-5 py-3 font-medium text-on-surface">{{ $row['payment_type_name'] }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">Rp {{ number_format($row['target'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">Rp {{ number_format($row['paid'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data font-bold">Rp {{ number_format($row['outstanding'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">{{ $row['achievement_label'] }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right"><button type="button" @click="open = !open" class="text-label-md font-label-md text-primary disabled:text-on-surface-variant" @disabled(count($row['details']) === 0)>Lihat Detail</button></td>
                                </tr>
                                @if(count($row['details']) > 0)
                                    <tr x-show="open" x-collapse><td colspan="6" class="bg-surface-container-low px-5 py-4">
                                        <div class="overflow-x-auto"><table class="w-full min-w-[680px] table-fixed text-body-sm"><colgroup><col class="w-[34%]"><col class="w-[16%]"><col class="w-[17%]"><col class="w-[17%]"><col class="w-[16%]"></colgroup><thead><tr class="text-on-surface-variant"><th class="pb-2 text-left">Nama Siswa</th><th class="pb-2 text-left">Kelas</th><th class="pb-2 text-right">Tagihan</th><th class="pb-2 text-right">Terbayar</th><th class="pb-2 text-right">Sisa</th></tr></thead><tbody class="divide-y divide-outline-variant">
                                            @foreach($row['details'] as $detail)<tr><td class="py-2">{{ $detail['student_name'] }}</td><td class="py-2">{{ $detail['class_name'] }}</td><td class="whitespace-nowrap py-2 text-right font-numeric-data">Rp {{ number_format($detail['target'], 0, ',', '.') }}</td><td class="whitespace-nowrap py-2 text-right font-numeric-data">Rp {{ number_format($detail['paid'], 0, ',', '.') }}</td><td class="whitespace-nowrap py-2 text-right font-numeric-data font-bold">Rp {{ number_format($detail['outstanding'], 0, ',', '.') }}</td></tr>@endforeach
                                        </tbody></table></div>
                                    </td></tr>
                                @endif
                            </tbody>
                        @empty
                            <tbody><tr><td colspan="6" class="px-5 py-8 text-center text-body-md text-on-surface-variant">Belum ada tagihan pada periode ini.</td></tr></tbody>
                        @endforelse
                        <tfoot><tr class="bg-primary-fixed text-label-md font-label-md text-on-primary-fixed"><td class="px-5 py-3">TOTAL</td><td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">Rp {{ number_format($sectionReport['totals']['target'], 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">Rp {{ number_format($sectionReport['totals']['paid'], 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">Rp {{ number_format($sectionReport['totals']['outstanding'], 0, ',', '.') }}</td><td class="whitespace-nowrap px-5 py-3 text-right font-numeric-data">{{ $sectionReport['totals']['achievement_label'] }}</td><td></td></tr></tfoot>
                    </table>
                </div>
            </section>
        @endforeach
    @elseif($activeTab === 'class')
        @php
            $formatClassRecapAmount = static fn (float|int $amount): string => $amount > 0 ? 'Rp '.number_format($amount, 0, ',', '.') : '-';
        @endphp
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
            <div>
                <p class="text-label-md font-label-md text-on-surface">Konteks Rekap Kelas</p>
                <p class="mt-1 max-w-3xl text-body-sm text-on-surface-variant">Siswa mengikuti riwayat enrollment pada tahun ajaran dan kelas yang dipilih. Pembayaran mengikuti periode tagihan, bukan tanggal transaksi.</p>
            </div>
            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="min-w-0">
                    <label for="class-recap-academic-year" class="block text-body-sm text-on-surface-variant">Tahun Ajaran</label>
                    <select id="class-recap-academic-year" wire:model.live="classRecapAcademicYearId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Pilih Tahun Ajaran</option>
                        @foreach($classRecapAcademicYearOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="class-recap-level" class="block text-body-sm text-on-surface-variant">Jenjang</label>
                    <select id="class-recap-level" wire:model.live="classRecapSchoolLevel" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        <option value="">Pilih Jenjang</option>
                        @foreach($classRecapLevelOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="class-recap-class" class="block text-body-sm text-on-surface-variant">Kelas</label>
                    <select id="class-recap-class" wire:model.live="classRecapSchoolClassId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary" @disabled($classRecapSchoolLevel === '')>
                        <option value="">Pilih Kelas</option>
                        @foreach($classRecapClassOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        @if($classRecapReport === null)
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">table_view</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Pilih jenjang dan kelas untuk menampilkan rekap.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Tahun ajaran aktif dipilih secara otomatis bila tersedia.</p>
            </section>
        @elseif($classRecapReport['student_count'] === 0)
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">group_off</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada siswa pada kelas ini.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Daftar siswa mengikuti enrollment {{ $classRecapReport['academic_year'] }} di kelas {{ $classRecapReport['school_class'] }}.</p>
            </section>
        @else
            <section class="rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                <header class="flex flex-col gap-2 border-b border-outline-variant px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-title-md font-title-md text-on-surface">Rekap {{ $classRecapReport['school_class'] }}</h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Tahun Ajaran {{ $classRecapReport['academic_year'] }} <span aria-hidden="true">&bull;</span> Jenjang {{ $classRecapReport['school_level'] }}. Kolom mengikuti jenis pembayaran yang aktif pada jenjang; ringkasan memakai tagihan terakhir yang tersimpan dan kolom bulan menampilkan pembayaran sesuai periode tagihan.</p>
                    </div>
                    <p class="text-body-sm text-on-surface-variant">{{ $classRecapReport['student_count'] }} siswa</p>
                </header>
                @php
                    $recapMonthlyTypes = $classRecapReport['payment_types']['monthly'];
                    $recapYearlyTypes = $classRecapReport['payment_types']['yearly'];
                    $recapOneTimeTypes = $classRecapReport['payment_types']['one_time'];
                    $recapMonthlyCount = count($recapMonthlyTypes);
                    $recapYearlyCount = count($recapYearlyTypes);
                    $recapOneTimeCount = count($recapOneTimeTypes);
                    $recapLastMonthlyId = $recapMonthlyCount > 0 ? $recapMonthlyTypes[$recapMonthlyCount - 1]['id'] : null;
                @endphp
                <div class="rounded-b-xl" x-data="classRecapStickyHeader()">
                    <div class="sticky top-16 z-30 bg-surface-container-low">
                        <div class="overflow-x-hidden" x-ref="cloneScroller">
                            <table class="recap-clone-table min-w-[6200px] border-collapse text-left text-body-sm">
                                <thead class="uppercase tracking-wider text-on-surface-variant">
                                    @include('livewire.partials.class-recap-thead', [
                                        'recapMonthlyTypes' => $recapMonthlyTypes,
                                        'recapYearlyTypes' => $recapYearlyTypes,
                                        'recapOneTimeTypes' => $recapOneTimeTypes,
                                        'recapMonthlyCount' => $recapMonthlyCount,
                                        'recapYearlyCount' => $recapYearlyCount,
                                        'recapOneTimeCount' => $recapOneTimeCount,
                                        'recapLastMonthlyId' => $recapLastMonthlyId,
                                        'classRecapReport' => $classRecapReport,
                                    ])
                                </thead>
                            </table>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-b-xl">
                        <div class="overflow-x-auto overflow-y-hidden overscroll-x-contain rounded-b-xl" x-ref="recapScroller">
                            <table class="recap-real-table min-w-[6200px] border-collapse text-left text-body-sm" x-ref="recapTable">
                                <thead class="recap-measure-head invisible uppercase tracking-wider text-on-surface-variant" aria-hidden="true">
                                    @include('livewire.partials.class-recap-thead', [
                                        'recapMonthlyTypes' => $recapMonthlyTypes,
                                        'recapYearlyTypes' => $recapYearlyTypes,
                                        'recapOneTimeTypes' => $recapOneTimeTypes,
                                        'recapMonthlyCount' => $recapMonthlyCount,
                                        'recapYearlyCount' => $recapYearlyCount,
                                        'recapOneTimeCount' => $recapOneTimeCount,
                                        'recapLastMonthlyId' => $recapLastMonthlyId,
                                        'classRecapReport' => $classRecapReport,
                                    ])
                                </thead>
                        <tbody class="divide-y divide-outline-variant text-on-surface">
                                    @foreach($classRecapReport['rows'] as $index => $row)
                                        <tr class="hover:bg-surface-container-low/50">
                                            <td class="sticky left-0 z-10 border-r border-outline-variant bg-surface-container-lowest px-3 py-3 text-center font-numeric-data">{{ $index + 1 }}</td>
                                            <td class="sticky left-14 z-10 border-r-2 border-outline bg-surface-container-lowest px-4 py-3 font-medium">{{ $row['student_name'] }}</td>
                                            @if($recapMonthlyCount > 0)
                                                @foreach($recapMonthlyTypes as $type)
                                                    <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data">{{ $formatClassRecapAmount($row['monthly_summary'][$type['id']]) }}</td>
                                                @endforeach
                                                <td class="whitespace-nowrap border-r-2 border-outline px-3 py-3 text-right font-numeric-data font-bold">{{ $formatClassRecapAmount($row['monthly_summary']['total']) }}</td>
                                                @foreach($classRecapReport['months'] as $month)
                                                    @foreach($recapMonthlyTypes as $type)
                                                        <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data {{ $type['id'] === $recapLastMonthlyId ? 'border-r border-outline-variant' : '' }}">{{ $formatClassRecapAmount($row['monthly_paid'][$month['key']][$type['id']]) }}</td>
                                                    @endforeach
                                                @endforeach
                                            @endif
                                            @foreach($recapYearlyTypes as $yearlyType)
                                                @foreach(['target', 'paid', 'remaining'] as $balanceKey)
                                                    <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data {{ $balanceKey === 'remaining' && $row['yearly'][$yearlyType['id']][$balanceKey] > 0 ? 'font-bold text-error' : '' }} {{ $balanceKey === 'remaining' ? ($loop->parent->last ? 'border-r-2 border-outline' : 'border-r border-outline-variant') : '' }}">{{ $formatClassRecapAmount($row['yearly'][$yearlyType['id']][$balanceKey]) }}</td>
                                                @endforeach
                                            @endforeach
                                            @foreach($recapOneTimeTypes as $oneTimeType)
                                                @foreach(['target', 'paid', 'remaining'] as $balanceKey)
                                                    <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data {{ $balanceKey === 'remaining' && $row['one_time'][$oneTimeType['id']][$balanceKey] > 0 ? 'font-bold text-error' : '' }}">{{ $formatClassRecapAmount($row['one_time'][$oneTimeType['id']][$balanceKey]) }}</td>
                                                @endforeach
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-primary bg-primary-fixed text-label-md font-label-md text-on-primary-fixed">
                                        <td colspan="2" class="sticky left-0 z-20 border-r-2 border-outline bg-primary-fixed px-4 py-3">JUMLAH</td>
                                        @if($recapMonthlyCount > 0)
                                            @foreach($recapMonthlyTypes as $type)
                                                <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data">{{ $formatClassRecapAmount($classRecapReport['totals']['monthly_summary'][$type['id']]) }}</td>
                                            @endforeach
                                            <td class="whitespace-nowrap border-r-2 border-outline px-3 py-3 text-right font-numeric-data font-bold">{{ $formatClassRecapAmount($classRecapReport['totals']['monthly_summary']['total']) }}</td>
                                            @foreach($classRecapReport['months'] as $month)
                                                @foreach($recapMonthlyTypes as $type)
                                                    <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data {{ $type['id'] === $recapLastMonthlyId ? 'border-r border-outline-variant' : '' }}">{{ $formatClassRecapAmount($classRecapReport['totals']['monthly_paid'][$month['key']][$type['id']]) }}</td>
                                                @endforeach
                                            @endforeach
                                        @endif
                                        @foreach($recapYearlyTypes as $yearlyType)
                                            @foreach(['target', 'paid', 'remaining'] as $balanceKey)
                                                <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data {{ $balanceKey === 'remaining' ? ($loop->parent->last ? 'border-r-2 border-outline' : 'border-r border-outline-variant') : '' }}">{{ $formatClassRecapAmount($classRecapReport['totals']['yearly'][$yearlyType['id']][$balanceKey]) }}</td>
                                            @endforeach
                                        @endforeach
                                        @foreach($recapOneTimeTypes as $oneTimeType)
                                            @foreach(['target', 'paid', 'remaining'] as $balanceKey)
                                                <td class="whitespace-nowrap px-3 py-3 text-right font-numeric-data">{{ $formatClassRecapAmount($classRecapReport['totals']['one_time'][$oneTimeType['id']][$balanceKey]) }}</td>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @elseif($activeTab === 'monthly')
        @php($activeMonthlyReport = $monthlyReport ?? $levelReport ?? $allUnitsReport)
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
            <div>
                <p class="text-label-md font-label-md text-on-surface">Periode Laporan Bulanan</p>
                <p class="mt-1 max-w-3xl text-body-sm text-on-surface-variant">Bulan mengikuti tanggal transaksi dicatat di Annur Management.</p>
                <p class="mt-2 text-body-sm text-on-surface-variant">Periode: <strong class="text-on-surface">{{ $activeMonthlyReport['month_label'] }}</strong> <span aria-hidden="true">&bull;</span> Unit: <strong class="text-on-surface">{{ $activeMonthlyReport['unit_name'] }}</strong></p>
            </div>
            <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2 {{ $monthlyMode === 'by_date' ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }}">
                    <div class="min-w-0">
                        <label for="report-month" class="block text-body-sm text-on-surface-variant">Bulan</label>
                        <select id="report-month" wire:model.live="reportMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($monthOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label for="report-year" class="block text-body-sm text-on-surface-variant">Tahun</label>
                        <select id="report-year" wire:model.live="reportYear" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($yearOptions as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($monthlyMode === 'by_date')
                    <div class="min-w-0">
                        <label for="report-level" class="block text-body-sm text-on-surface-variant">Jenjang</label>
                        <select id="report-level" wire:model.live="schoolLevel" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($levelOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
            </div>
            @error('reportMonth') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            @error('reportYear') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
            <div class="mt-5 border-t border-outline-variant pt-4">
                <p class="mb-2 text-label-sm font-label-sm uppercase tracking-wider text-on-surface-variant">Tampilan Laporan</p>
                <nav class="flex gap-1 overflow-x-auto" aria-label="Mode laporan bulanan">
                    @foreach(['by_date' => 'Per Tanggal', 'by_level' => 'Per Jenjang', 'all_units' => 'Seluruh Unit'] as $mode => $label)
                        <button type="button" wire:click="setMonthlyMode('{{ $mode }}')" class="relative whitespace-nowrap px-4 py-3 text-label-lg font-label-lg transition-colors {{ $monthlyMode === $mode ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}">
                            {{ $label }}
                            @if($monthlyMode === $mode)<span class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary"></span>@endif
                        </button>
                    @endforeach
                </nav>
            </div>
        </section>

        @if($monthlyMode === 'by_date')
        @if($monthlyReport['detail_count'] === 0)
            <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-8 text-center">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant">calendar_month</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada transaksi sekolah pada bulan ini.</p>
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
                                    @foreach($dateGroup['banks'] as $bank)
                                        <tr class="align-top">
                                            @if($loop->first)
                                                <td rowspan="{{ $dateGroup['bank_count'] }}" class="px-3 py-2 align-middle text-label-md font-label-md text-on-surface sm:px-4">
                                                    {{ $dateGroup['date_label'] }}
                                                </td>
                                            @endif
                                            <td class="px-3 py-2 text-body-sm font-medium text-on-surface">{{ $bank['bank_label'] }}</td>
                                            @foreach($monthlyReport['categories'] as $category)
                                                <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $bank['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bank['amounts'][$category['key']], 0, ',', '.') : '' }}</td>
                                            @endforeach
                                            <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold text-on-surface sm:px-4">{{ $bank['total'] > 0 ? 'Rp '.number_format($bank['total'], 0, ',', '.') : '' }}</td>
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
                                <td class="px-3 py-2 sm:px-4">GRAND TOTAL TUNAI</td>
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

        @elseif($monthlyMode === 'by_level')
        <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
            <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">PENERIMAAN BANK</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-max border-collapse text-left">
                    <caption class="sr-only">Penerimaan Bank per Jenjang</caption>
                    <thead><tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                        <th class="min-w-[120px] px-3 py-2 sm:px-4">Jenjang</th><th class="min-w-[150px] px-3 py-2">Bank</th>
                        @foreach($levelReport['categories'] as $category)<th class="min-w-[110px] px-3 py-2 text-right">{{ $category['name'] }}</th>@endforeach
                        <th class="min-w-[120px] px-3 py-2 text-right">Total</th><th class="min-w-[140px] px-3 py-2 text-right sm:px-4">Total Jenjang</th>
                    </tr></thead>
                    @if($levelReport['bank']['account_count'] === 0)
                        <tbody><tr><td colspan="{{ count($levelReport['categories']) + 4 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Belum ada rekening bank yang dikonfigurasi.</td></tr></tbody>
                    @else
                    @forelse($levelReport['bank']['levels'] as $level)
                        <tbody class="divide-y divide-outline-variant border-t border-outline-variant">
                            @foreach($level['banks'] as $bank)
                                <tr>
                                    @if($loop->first)<td rowspan="{{ $level['bank_count'] }}" class="px-3 py-2 text-center align-middle font-bold text-on-surface sm:px-4">{{ $level['level_label'] }}</td>@endif
                                    <td class="px-3 py-2 font-medium text-on-surface">{{ $bank['bank_label'] }}</td>
                                    @foreach($levelReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $bank['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bank['amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold">{{ $bank['total'] > 0 ? 'Rp '.number_format($bank['total'], 0, ',', '.') : '' }}</td>
                                    @if($loop->first)<td rowspan="{{ $level['bank_count'] }}" class="whitespace-nowrap px-3 py-2 text-right align-middle font-numeric-data font-bold sm:px-4">Rp {{ number_format($level['bank_total'], 0, ',', '.') }}</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="{{ count($levelReport['categories']) + 4 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Tidak ada penerimaan bank pada bulan ini.</td></tr></tbody>
                    @endforelse
                    @endif
                    <tfoot><tr class="border-t-2 border-primary/30 bg-primary-fixed text-label-md font-label-md text-on-primary-fixed">
                        <td colspan="2" class="px-3 py-2 sm:px-4">TOTAL PENERIMAAN BANK</td>
                        @foreach($levelReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $levelReport['bank']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($levelReport['bank']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach
                        <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold">{{ $levelReport['bank']['total'] > 0 ? 'Rp '.number_format($levelReport['bank']['total'], 0, ',', '.') : '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $levelReport['bank']['total'] > 0 ? 'Rp '.number_format($levelReport['bank']['total'], 0, ',', '.') : '' }}</td>
                    </tr></tfoot>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
            <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">PENERIMAAN TUNAI</h2>
            <div class="overflow-x-auto"><table class="w-full min-w-max border-collapse text-left">
                <caption class="sr-only">Penerimaan Tunai per Jenjang</caption>
                <thead><tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant"><th class="min-w-[120px] px-3 py-2 sm:px-4">Jenjang</th>@foreach($levelReport['categories'] as $category)<th class="min-w-[110px] px-3 py-2 text-right">{{ $category['name'] }}</th>@endforeach<th class="min-w-[120px] px-3 py-2 text-right sm:px-4">Total</th></tr></thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse($levelReport['cash']['levels'] as $level)
                        <tr><td class="px-3 py-2 text-center align-middle font-bold text-on-surface sm:px-4">{{ $level['level_label'] }}</td>@foreach($levelReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $level['cash_amounts'][$category['key']] > 0 ? 'Rp '.number_format($level['cash_amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">Rp {{ number_format($level['cash_total'], 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td colspan="{{ count($levelReport['categories']) + 2 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Tidak ada penerimaan tunai pada bulan ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot><tr class="border-t-2 border-primary/30 bg-primary-fixed text-label-md font-label-md text-on-primary-fixed"><td class="px-3 py-2 sm:px-4">TOTAL PENERIMAAN TUNAI</td>@foreach($levelReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $levelReport['cash']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($levelReport['cash']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $levelReport['cash']['total'] > 0 ? 'Rp '.number_format($levelReport['cash']['total'], 0, ',', '.') : '' }}</td></tr></tfoot>
            </table></div>
        </section>

        <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
            <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">RINGKASAN TOTAL</h2>
            <dl class="divide-y divide-outline-variant text-body-sm">
                <div class="flex items-center justify-between gap-6 px-4 py-2"><dt>TOTAL PENERIMAAN BANK</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $levelReport['bank']['total'] > 0 ? 'Rp '.number_format($levelReport['bank']['total'], 0, ',', '.') : '' }}</dd></div>
                <div class="flex items-center justify-between gap-6 px-4 py-2"><dt>TOTAL PENERIMAAN TUNAI</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $levelReport['cash']['total'] > 0 ? 'Rp '.number_format($levelReport['cash']['total'], 0, ',', '.') : '' }}</dd></div>
                <div class="flex items-center justify-between gap-6 bg-primary-fixed px-4 py-2 text-label-md font-label-md text-on-primary-fixed"><dt>GRAND TOTAL</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $levelReport['grand_total'] > 0 ? 'Rp '.number_format($levelReport['grand_total'], 0, ',', '.') : '' }}</dd></div>
            </dl>
        </section>
        @else
            @if($allUnitsReport['detail_count'] === 0)
                <section class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-8 text-center">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant">account_balance_wallet</span>
                    <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada penerimaan siswa pada {{ $allUnitsReport['month_label'] }}.</p>
                    <p class="mt-1 text-body-md text-on-surface-variant">Pilih bulan lain untuk melihat penerimaan yang sudah tercatat.</p>
                </section>
            @else
                <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">PENERIMAAN BANK</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max border-collapse text-left">
                            <caption class="sr-only">Penerimaan Bank Seluruh Unit</caption>
                            <thead><tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                <th class="min-w-[150px] px-3 py-2 sm:px-4">Bank</th>
                                @foreach($allUnitsReport['categories'] as $category)<th class="min-w-[110px] px-3 py-2 text-right">{{ $category['name'] }}</th>@endforeach
                                <th class="min-w-[120px] px-3 py-2 text-right sm:px-4">Total</th>
                            </tr></thead>
                            @forelse($allUnitsReport['bank']['rows'] as $bank)
                                <tbody class="divide-y divide-outline-variant"><tr>
                                    <td class="px-3 py-2 font-medium text-on-surface sm:px-4">{{ $bank['bank_label'] }}</td>
                                    @foreach($allUnitsReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $bank['amounts'][$category['key']] > 0 ? 'Rp '.number_format($bank['amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $bank['total'] > 0 ? 'Rp '.number_format($bank['total'], 0, ',', '.') : '' }}</td>
                                </tr></tbody>
                            @empty
                                <tbody><tr><td colspan="{{ count($allUnitsReport['categories']) + 2 }}" class="px-4 py-5 text-center text-body-sm text-on-surface-variant">Tidak ada penerimaan bank pada bulan ini.</td></tr></tbody>
                            @endforelse
                            <tfoot><tr class="border-t-2 border-primary/30 bg-primary-fixed text-label-md font-label-md text-on-primary-fixed">
                                <td class="px-3 py-2 sm:px-4">TOTAL PENERIMAAN BANK</td>
                                @foreach($allUnitsReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $allUnitsReport['bank']['category_totals'][$category['key']] > 0 ? 'Rp '.number_format($allUnitsReport['bank']['category_totals'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach
                                <td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $allUnitsReport['bank']['total'] > 0 ? 'Rp '.number_format($allUnitsReport['bank']['total'], 0, ',', '.') : '' }}</td>
                            </tr></tfoot>
                        </table>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">PENERIMAAN TUNAI</h2>
                    <div class="overflow-x-auto"><table class="w-full min-w-max border-collapse text-left">
                        <caption class="sr-only">Penerimaan Tunai Seluruh Unit</caption>
                        <thead><tr class="border-y border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">@foreach($allUnitsReport['categories'] as $category)<th class="min-w-[110px] px-3 py-2 text-right">{{ $category['name'] }}</th>@endforeach<th class="min-w-[120px] px-3 py-2 text-right sm:px-4">Total</th></tr></thead>
                        <tbody><tr>@foreach($allUnitsReport['categories'] as $category)<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data">{{ $allUnitsReport['cash']['amounts'][$category['key']] > 0 ? 'Rp '.number_format($allUnitsReport['cash']['amounts'][$category['key']], 0, ',', '.') : '' }}</td>@endforeach<td class="whitespace-nowrap px-3 py-2 text-right font-numeric-data font-bold sm:px-4">{{ $allUnitsReport['cash']['total'] > 0 ? 'Rp '.number_format($allUnitsReport['cash']['total'], 0, ',', '.') : '' }}</td></tr></tbody>
                    </table></div>
                </section>

                <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                    <h2 class="border-b border-outline-variant px-4 py-3 text-title-sm font-title-sm text-on-surface">RINGKASAN TOTAL</h2>
                    <dl class="divide-y divide-outline-variant text-body-sm">
                        <div class="flex items-center justify-between gap-6 px-4 py-2"><dt>TOTAL PENERIMAAN BANK</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $allUnitsReport['bank']['total'] > 0 ? 'Rp '.number_format($allUnitsReport['bank']['total'], 0, ',', '.') : '' }}</dd></div>
                        <div class="flex items-center justify-between gap-6 px-4 py-2"><dt>TOTAL PENERIMAAN TUNAI</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">{{ $allUnitsReport['cash']['total'] > 0 ? 'Rp '.number_format($allUnitsReport['cash']['total'], 0, ',', '.') : '' }}</dd></div>
                        <div class="flex items-center justify-between gap-6 bg-primary-fixed px-4 py-2 text-label-md font-label-md text-on-primary-fixed"><dt>GRAND TOTAL</dt><dd class="whitespace-nowrap text-right font-numeric-data font-bold">Rp {{ number_format($allUnitsReport['grand_total'], 0, ',', '.') }}</dd></div>
                    </dl>
                </section>
            @endif
        @endif
    @elseif($activeTab === 'bank')
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
                <div>
                    <p class="text-label-md font-label-md text-on-surface">Periode Rekap Bank</p>
                    <p class="mt-0.5 text-body-sm text-on-surface-variant">Tanggal mengikuti tanggal pembayaran aktual untuk rekonsiliasi bank.</p>
                    <p class="mt-2 text-body-sm text-on-surface-variant">Periode: <strong class="text-on-surface">{{ $bankReport['period_label'] }}</strong></p>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="min-w-0">
                        <label for="bank-start-date" class="block text-body-sm text-on-surface-variant">Dari Tanggal</label>
                        <input id="bank-start-date" type="date" wire:model.live="bankStartDate" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @error('bankStartDate') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label for="bank-end-date" class="block text-body-sm text-on-surface-variant">Sampai Tanggal</label>
                        <input id="bank-end-date" type="date" wire:model.live="bankEndDate" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @error('bankEndDate') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label for="bank-filter" class="block text-body-sm text-on-surface-variant">Bank / Channel</label>
                        <select id="bank-filter" wire:model.live="bankFilter" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            <option value="all">Semua Bank</option>
                            <option value="cash">Tunai / Cash</option>
                            @foreach($bankFilterOptions as $bankFilterOption)
                                <option value="{{ $bankFilterOption->id }}">{{ $bankFilterOption->name }} • {{ $bankFilterOption->displayAccountNumber() ?? '-' }}</option>
                            @endforeach
                        </select>
                        @error('bankFilter') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <p class="text-label-md text-on-surface-variant">Tunai / Cash</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-surface">Rp {{ number_format($bankReport['cash_total'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <p class="text-label-md text-on-surface-variant">Transfer / Debet</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-surface">Rp {{ number_format($bankReport['bank_total'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-primary/30 bg-primary-fixed p-5">
                <p class="text-label-md text-on-primary-fixed-variant">Total Penerimaan Aktual</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-primary-fixed">Rp {{ number_format($bankReport['grand_total'], 0, ',', '.') }}</p>
                <p class="mt-1 text-body-sm text-on-primary-fixed-variant">{{ $bankReport['transaction_count'] }} transaksi</p>
            </div>
        </section>

        @if($bankReport['transaction_count'] === 0)
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">account_balance</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada pembayaran aktual pada periode ini.</p>
                <p class="mt-1 text-body-md text-on-surface-variant">Pilih tanggal lain untuk mencocokkan transaksi dengan mutasi bank.</p>
            </section>
        @else
            @foreach($bankReport['sections'] as $section)
                @if($section['transaction_count'] > 0)
                    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
                        <header class="flex flex-col gap-1 border-b border-outline-variant bg-surface-container-low px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <h2 class="text-headline-sm font-headline-sm text-on-surface">{{ $section['bank_name'] }}</h2>
                                @if($section['bank_id'] !== null)
                                    <p class="text-body-sm text-on-surface-variant">{{ $section['bank_label'] }}</p>
                                @endif
                            </div>
                            <p class="font-numeric-data text-label-lg font-label-lg text-on-surface">Rp {{ number_format($section['total'], 0, ',', '.') }}</p>
                        </header>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] border-collapse text-left">
                                <colgroup>
                                    <col>
                                    <col class="w-[120px]">
                                    <col class="w-[180px]">
                                </colgroup>
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low text-label-sm uppercase tracking-wider text-on-surface-variant">
                                        <th class="px-5 py-3 sm:px-6">Tanggal Pembayaran</th>
                                        <th class="px-5 py-3 text-center">Transaksi</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Jumlah</th>
                                    </tr>
                                </thead>
                                @foreach($section['rows'] as $row)
                                    <tbody x-data="{ open: false }" class="border-b border-outline-variant last:border-b-0">
                                        <tr
                                            role="button"
                                            tabindex="0"
                                            @click="open = ! open"
                                            @keydown.enter.prevent="open = ! open"
                                            @keydown.space.prevent="open = ! open"
                                            class="cursor-pointer transition-colors hover:bg-surface-container-low"
                                            :aria-expanded="open"
                                        >
                                            <td class="px-5 py-3 font-medium text-on-surface sm:px-6">
                                                <span class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform" :class="open ? 'rotate-90' : ''">chevron_right</span>
                                                    {{ $row['date']->locale('id')->translatedFormat('d M Y') }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-center font-numeric-data text-on-surface">{{ $row['transaction_count'] }}</td>
                                            <td class="px-5 py-3 text-right font-numeric-data font-bold text-on-surface sm:px-6">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                                        </tr>
                                        <tr x-cloak x-show="open" class="bg-surface-container-low/50">
                                            <td colspan="3" class="border-t border-outline-variant px-5 py-3 sm:px-6">
                                                <div class="overflow-x-auto">
                                                    <table class="w-full min-w-[820px] table-fixed text-left text-body-sm">
                                                        <colgroup>
                                                            <col class="w-[6%]">
                                                            <col class="w-[28%]">
                                                            <col class="w-[13%]">
                                                            <col class="w-[17%]">
                                                            <col class="w-[22%]">
                                                            <col class="w-[14%]">
                                                        </colgroup>
                                                        <thead class="text-label-sm text-on-surface-variant">
                                                            <tr>
                                                                <th class="pb-2 pr-4 text-center">No.</th>
                                                                <th class="pb-2 pr-4">Nama</th>
                                                                <th class="pb-2 pr-4">Tanggal Pembayaran</th>
                                                                <th class="pb-2 pr-4">Tanggal Dicatat</th>
                                                                <th class="pb-2 pr-4">No. Kwitansi</th>
                                                                <th class="pb-2 text-right">Nominal</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-outline-variant/70 text-on-surface">
                                                            @foreach($row['details'] as $detailIndex => $detail)
                                                                <tr>
                                                                    <td class="py-2 pr-4 text-center font-numeric-data">{{ $detailIndex + 1 }}</td>
                                                                    <td class="py-2 pr-4">
                                                                        <span class="flex items-center justify-between gap-3">
                                                                            <span class="min-w-0 truncate">
                                                                                @if($detail['source'] === 'daycare')
                                                                                    <a href="{{ route('daycare.payment.show', $detail['payment_id']) }}" class="font-medium text-primary hover:underline">{{ $detail['name'] }}</a>
                                                                                @elseif($detail['source'] === 'prospective')
                                                                                    <a href="{{ route('pembayaran.prospective.show', $detail['payment_id']) }}" class="font-medium text-primary hover:underline">{{ $detail['name'] }}</a>
                                                                                @else
                                                                                    <a href="{{ route('pembayaran.show', $detail['payment_id']) }}" class="font-medium text-primary hover:underline">{{ $detail['name'] }}</a>
                                                                                @endif
                                                                            </span>
                                                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap {{ $detail['source'] === 'daycare' ? 'bg-indigo-100 text-indigo-800' : ($detail['source'] === 'prospective' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700') }}">{{ $detail['source'] === 'daycare' ? 'DAYCARE' : ($detail['source'] === 'prospective' ? 'CALON SISWA' : 'SISWA') }}</span>
                                                                        </span>
                                                                    </td>
                                                                    <td class="py-2 pr-4">{{ $detail['payment_date']->locale('id')->translatedFormat('d M Y') }}</td>
                                                                    <td class="py-2 pr-4">{{ $detail['recorded_at']->locale('id')->translatedFormat('d M Y H:i') }}</td>
                                                                    <td class="py-2 pr-4 font-numeric-data">{{ $detail['receipt_number'] }}</td>
                                                                    <td class="py-2 text-right font-numeric-data font-medium">Rp {{ number_format($detail['amount'], 0, ',', '.') }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforeach
                                <tfoot>
                                    <tr class="border-t border-outline-variant bg-surface-container-low text-label-md font-label-md text-on-surface">
                                        <td colspan="2" class="px-5 py-3 sm:px-6">TOTAL {{ strtoupper($section['bank_name']) }}</td>
                                        <td class="px-5 py-3 text-right font-numeric-data font-bold sm:px-6">Rp {{ number_format($section['total'], 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                @endif
            @endforeach
        @endif
    @else
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm sm:p-6">
                <div>
                    <p class="text-label-md font-label-md text-on-surface">Periode Laporan</p>
                    <p class="mt-0.5 text-body-sm text-on-surface-variant">Tanggal mengikuti waktu transaksi dicatat di Annur Management.</p>
                    <p class="mt-2 text-body-sm text-on-surface-variant">{{ $report['period_title'] }}: <strong class="text-on-surface">{{ $report['period_label'] }}</strong> <span aria-hidden="true">&bull;</span> Unit: <strong class="text-on-surface">{{ $report['unit_name'] }}</strong></p>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 border-t border-outline-variant pt-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="min-w-0">
                        <label for="report-start-date" class="block text-body-sm text-on-surface-variant">Dari Tanggal</label>
                        <input id="report-start-date" type="date" wire:model.live="reportStartDate" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @error('reportStartDate') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label for="report-end-date" class="block text-body-sm text-on-surface-variant">Sampai Tanggal</label>
                        <input id="report-end-date" type="date" wire:model.live="reportEndDate" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                        @error('reportEndDate') <p class="mt-1 text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="min-w-0">
                        <label for="report-level" class="block text-body-sm text-on-surface-variant">Jenjang</label>
                        <select id="report-level" wire:model.live="schoolLevel" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md shadow-sm focus:border-primary focus:ring-primary">
                            @foreach($levelOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <p class="text-label-md text-on-surface-variant">Tunai / Cash</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-surface">Rp {{ number_format($report['channels']['cash']['total'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <p class="text-label-md text-on-surface-variant">Transfer / Debet</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-surface">Rp {{ number_format($report['channels']['transfer']['total'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-primary/30 bg-primary-fixed p-5">
                <p class="text-label-md text-on-primary-fixed-variant">Total Penerimaan</p>
                <p class="mt-2 font-numeric-data text-headline-md font-headline-md text-on-primary-fixed">Rp {{ number_format($report['grand_total'], 0, ',', '.') }}</p>
                <p class="mt-1 text-body-sm text-on-primary-fixed-variant">{{ $report['transaction_count'] }} transaksi &bull; {{ $report['detail_count'] }} rincian</p>
            </div>
        </section>

        @if($report['detail_count'] === 0)
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                <span class="material-symbols-outlined text-5xl text-on-surface-variant">receipt_long</span>
                <p class="mt-3 text-headline-sm font-headline-sm text-on-surface">Belum ada transaksi sekolah pada periode ini.</p>
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
                                            <tr x-data="{ open: false }" class="align-top">
                                                <td colspan="3" class="p-0">
                                                    <button type="button" @click="open = ! open" class="grid w-full grid-cols-[55%_15%_30%] items-center py-3 text-left transition-colors hover:bg-surface-container-low" :aria-expanded="open">
                                                        <span class="flex items-center gap-2 px-5 font-medium text-on-surface sm:px-6">
                                                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform" :class="open ? 'rotate-90' : ''">chevron_right</span>
                                                            {{ $category['name'] }}
                                                        </span>
                                                        <span class="px-3 text-center text-body-sm text-on-surface-variant">{{ count($category['details']) }}</span>
                                                        <span class="px-5 text-right font-numeric-data font-bold text-on-surface sm:px-6">Rp {{ number_format($category['total'], 0, ',', '.') }}</span>
                                                    </button>
                                                    <div x-cloak x-show="open" x-collapse class="border-t border-outline-variant bg-surface-container-low/50">
                                                        <div class="overflow-x-auto px-5 py-3 sm:px-6">
                                                            <table class="w-full min-w-[860px] table-fixed text-left text-body-sm">
                                                                <colgroup>
                                                                    <col class="w-[18%]">
                                                                    <col class="w-[22%]">
                                                                    <col class="w-[12%]">
                                                                    <col class="w-[20%]">
                                                                    <col class="w-[14%]">
                                                                    <col class="w-[14%]">
                                                                </colgroup>
                                                                <thead class="text-label-sm text-on-surface-variant">
                                                                    <tr>
                                                                        <th class="pb-2 pr-4">No. Kwitansi</th>
                                                                        <th class="pb-2 pr-4">Siswa</th>
                                                                        <th class="pb-2 pr-4">Kelas</th>
                                                                        <th class="pb-2 pr-4">Detail</th>
                                                                        <th class="pb-2 pr-4">Penerimaan</th>
                                                                        <th class="pb-2 text-right">Nominal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-outline-variant/70 text-on-surface">
                                                                    @foreach($category['details'] as $detail)
                                                                        <tr>
                                                                            <td class="py-2 pr-4 font-numeric-data font-medium">{{ $detail['receipt_number'] }}</td>
                                                                            <td class="py-2 pr-4">{{ $detail['student_name'] }}</td>
                                                                            <td class="py-2 pr-4">{{ $detail['class_name'] }}</td>
                                                                            <td class="py-2 pr-4">{{ $detail['detail_label'] }}</td>
                                                                            <td class="py-2 pr-4">{{ $detail['bank_label'] }}</td>
                                                                            <td class="py-2 text-right font-numeric-data font-medium">Rp {{ number_format($detail['amount'], 0, ',', '.') }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
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
