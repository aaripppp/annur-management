<div>
    <header class="mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-[0.14em] text-primary">Administrasi Siswa</p>
            <h1 class="text-2xl font-semibold tracking-tight text-on-surface md:text-3xl">Kelayakan Ujian</h1>
            <p class="mt-1.5 max-w-3xl text-sm leading-6 text-on-surface-variant md:text-base">Periksa siswa yang telah memenuhi kriteria pembayaran.</p>
        </div>
        @if ($academicYear)
            <button type="button" wire:click="openRequirements('{{ $filterLevel ?: 'TK' }}')" wire:loading.attr="disabled" wire:target="openRequirements" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                <span class="material-symbols-outlined text-[18px]">tune</span>
                Atur Kriteria
            </button>
        @endif
    </header>

    @if (session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" class="fixed inset-x-4 top-20 z-50 flex items-center gap-3 rounded-xl border border-secondary bg-secondary-container px-4 py-3 text-sm text-on-secondary-container shadow-lg sm:left-auto sm:right-6 sm:min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if ($academicYear)
        <section class="mb-5 overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
            <div class="flex flex-col gap-1 border-b border-outline-variant bg-surface-container-low px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p class="text-sm font-semibold text-on-surface">Kriteria Aktif</p>
                <p class="text-xs text-on-surface-variant">Tahun Ajaran {{ $academicYear->year }}</p>
            </div>
            <div class="overflow-x-auto p-4 sm:p-5">
                <div class="flex min-w-max gap-2">
                    <button type="button" wire:click="$set('filterLevel', '')" class="inline-flex h-10 items-center rounded-lg border px-3.5 text-sm font-medium {{ $filterLevel === '' ? 'border-primary bg-primary-fixed text-primary' : 'border-outline-variant text-on-surface hover:bg-surface-container-low' }}">Semua Jenjang</button>
                    @foreach ($levels as $level)
                        <button type="button" wire:click="$set('filterLevel', '{{ $level->value }}')" wire:key="level-filter-{{ $level->value }}" class="inline-flex h-10 items-center gap-2 rounded-lg border px-3.5 text-sm font-medium {{ $filterLevel === $level->value ? 'border-primary bg-primary-fixed text-primary' : 'border-outline-variant text-on-surface hover:bg-surface-container-low' }}">
                            <span>{{ $level->value }}</span>
                            <span class="inline-flex min-w-6 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $configuredCounts->get($level->value, 0) > 0 ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' }}">{{ $configuredCounts->get($level->value, 0) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! $academicYear)
        <div class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-5 py-12 text-center sm:py-14">
            <span class="material-symbols-outlined mb-3 text-4xl text-outline">calendar_month</span>
            <h2 class="text-lg font-semibold text-on-surface">Belum ada tahun ajaran aktif</h2>
            <p class="mx-auto mt-1.5 max-w-md text-sm text-on-surface-variant">Aktifkan tahun ajaran untuk mulai mengatur dan memeriksa kriteria kelayakan.</p>
        </div>
    @else
        @php
            $eligiblePercentage = $summaryCounts['total'] > 0 ? ($summaryCounts['eligible'] / $summaryCounts['total']) * 100 : 0;
            $notEligiblePercentage = $summaryCounts['total'] > 0 ? ($summaryCounts['not_eligible'] / $summaryCounts['total']) * 100 : 0;
        @endphp
        <section class="mb-5 grid grid-cols-1 gap-3 lg:grid-cols-3">
            <div class="flex min-h-28 items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-fixed text-on-surface"><span class="material-symbols-outlined text-[22px]">groups</span></div>
                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Total Siswa</p><p class="mt-1 font-numeric-data text-2xl font-semibold text-on-surface">{{ number_format($summaryCounts['total'], 0, ',', '.') }}</p><p class="mt-1 text-xs leading-4 text-on-surface-variant">Seluruh siswa pada filter aktif</p></div>
            </div>
            <div class="flex min-h-28 items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-secondary-container text-on-surface"><span class="material-symbols-outlined text-[22px]">verified</span></div>
                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Memenuhi</p><p class="mt-1 font-numeric-data text-2xl font-semibold text-secondary">{{ number_format($summaryCounts['eligible'], 0, ',', '.') }}</p><p class="mt-1 text-xs leading-4 text-on-surface-variant">{{ number_format($eligiblePercentage, 0, ',', '.') }}% dari total siswa</p></div>
            </div>
            <div class="flex min-h-28 items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:items-start">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-error-container text-on-surface"><span class="material-symbols-outlined text-[22px]">pending_actions</span></div>
                <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Belum Memenuhi</p><p class="mt-1 font-numeric-data text-2xl font-semibold text-error">{{ number_format($summaryCounts['not_eligible'], 0, ',', '.') }}</p><p class="mt-1 text-xs leading-4 text-on-surface-variant">{{ number_format($notEligiblePercentage, 0, ',', '.') }}% dari total siswa</p></div>
            </div>
        </section>

        @if (! $hasActiveCriteria)
            <div class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-5 py-10 text-center sm:py-12">
                <span class="material-symbols-outlined mb-3 text-4xl text-outline">rule</span>
                <h2 class="text-lg font-semibold text-on-surface">Belum ada kriteria aktif</h2>
                <p class="mx-auto mt-1.5 max-w-md text-sm text-on-surface-variant">Atur kriteria per jenjang untuk mulai memeriksa kelayakan siswa.</p>
                <button type="button" wire:click="openRequirements('{{ $filterLevel ?: 'TK' }}')" class="mt-5 inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-on-primary"><span class="material-symbols-outlined text-[18px]">tune</span>Atur Kriteria</button>
            </div>
        @else
        <section class="relative overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
            <div class="border-b border-outline-variant p-4 sm:p-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(280px,2fr)_minmax(130px,0.7fr)_minmax(160px,0.9fr)_minmax(160px,0.9fr)_auto] xl:items-end">
                    <label class="block sm:col-span-2 xl:col-span-1">
                        <span class="mb-1.5 block text-xs font-medium text-on-surface-variant">Cari Siswa</span>
                        <span class="relative block"><span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-on-surface-variant">search</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIS, atau kelas..." class="h-10 w-full rounded-lg border-outline-variant py-0 pl-10 pr-4 text-sm focus:border-primary focus:ring-primary"></span>
                    </label>
                    <label class="block"><span class="mb-1.5 block text-xs font-medium text-on-surface-variant">Jenjang</span><select wire:model.live="filterLevel" class="h-10 w-full rounded-lg border-outline-variant py-0 text-sm focus:border-primary focus:ring-primary"><option value="">Semua Jenjang</option>@foreach ($levels as $level)<option value="{{ $level->value }}">{{ $level->value }}</option>@endforeach</select></label>
                    <label class="block"><span class="mb-1.5 block text-xs font-medium text-on-surface-variant">Kelas</span><select wire:model.live="filterClassId" class="h-10 w-full rounded-lg border-outline-variant py-0 text-sm focus:border-primary focus:ring-primary"><option value="">Semua Kelas</option>@foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach</select></label>
                    <label class="block"><span class="mb-1.5 block text-xs font-medium text-on-surface-variant">Status</span><select wire:model.live="filterStatus" class="h-10 w-full rounded-lg border-outline-variant py-0 text-sm focus:border-primary focus:ring-primary"><option value="">Semua Status</option><option value="eligible">Memenuhi</option><option value="not_eligible">Belum Memenuhi</option></select></label>
                    <button type="button" wire:click="resetFilters" wire:loading.attr="disabled" wire:target="resetFilters" class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-outline-variant px-4 text-sm font-medium text-on-surface transition-colors hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-wait disabled:opacity-60"><span class="material-symbols-outlined text-[17px]">restart_alt</span>Reset</button>
                </div>
            </div>

            <div class="hidden overflow-x-auto xl:block">
                <table class="w-full min-w-[900px] table-fixed text-left text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="w-14 px-4 py-3 text-center font-semibold">No</th>
                            <th class="w-[31%] px-4 py-3 font-semibold">Siswa</th>
                            <th class="w-[20%] px-4 py-3 font-semibold">Jenjang / Kelas</th>
                            <th class="w-[24%] px-4 py-3 font-semibold">Progres</th>
                            <th class="w-[17%] px-4 py-3 font-semibold">Status</th>
                            <th class="w-24 px-4 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse ($students as $student)
                            @php
                                $summary = $summaries->get($student->id);
                            @endphp
                            <tr wire:key="exam-student-{{ $student->id }}" class="transition-colors hover:bg-surface-container-low/60">
                                <td class="px-4 py-3.5 text-center font-numeric-data text-on-surface-variant">{{ ($students->firstItem() ?? 1) + $loop->index }}</td>
                                <td class="px-4 py-3.5">
                                    <p class="truncate font-semibold text-on-surface" title="{{ $student->nama_lengkap }}">{{ $student->nama_lengkap }}</p>
                                    <p class="mt-0.5 text-xs text-on-surface-variant">NIS {{ $student->nis ?: '-' }}</p>
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex max-w-full rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-medium text-on-surface">{{ $summary['school_level'] ?? '-' }} / {{ $summary['class_name'] ?? '-' }}</span></td>
                                <td class="px-4 py-3.5">
                                    <div class="flex max-w-[190px] items-center gap-2.5">
                                        <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-surface-container-high" role="progressbar" aria-valuenow="{{ $summary['progress_percentage'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-primary" style="width: {{ $summary['progress_percentage'] ?? 0 }}%"></div></div>
                                        <span class="w-10 text-right font-numeric-data text-xs font-semibold text-on-surface">{{ number_format($summary['progress_percentage'] ?? 0, 0) }}%</span>
                                    </div>
                                    <p class="mt-1 text-xs text-on-surface-variant">{{ $summary['satisfied_count'] ?? 0 }}/{{ $summary['applicable_count'] ?? 0 }} terpenuhi</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($summary['is_eligible'] ?? false)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-secondary-container px-2.5 py-1 text-xs font-semibold text-on-secondary-container"><span class="material-symbols-outlined text-[15px]">check_circle</span>Memenuhi</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-error-container px-2.5 py-1 text-xs font-semibold text-on-error-container"><span class="material-symbols-outlined text-[15px]">cancel</span>Belum Memenuhi</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right"><button type="button" wire:click="showDetail({{ $student->id }})" wire:loading.attr="disabled" wire:target="showDetail({{ $student->id }})" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-primary/40 px-3 text-xs font-semibold text-primary transition-colors hover:border-primary hover:bg-primary-fixed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-wait disabled:opacity-60"><span class="material-symbols-outlined text-[16px]">visibility</span>Detail</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center"><span class="material-symbols-outlined mb-2 text-3xl text-outline">person_search</span><p class="font-medium text-on-surface">Belum ada siswa yang cocok dengan filter ini.</p><p class="mt-1 text-sm text-on-surface-variant">Coba ubah jenjang, kelas, status, atau kata pencarian.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-outline-variant xl:hidden">
                @forelse ($students as $student)
                    @php
                        $summary = $summaries->get($student->id);
                    @endphp
                    <article wire:key="exam-student-mobile-{{ $student->id }}" class="p-4 sm:p-5">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-on-surface">{{ $student->nama_lengkap }}</p><p class="mt-0.5 text-xs text-on-surface-variant">NIS {{ $student->nis ?: '-' }}</p></div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-medium text-on-surface">{{ $summary['school_level'] ?? '-' }} / {{ $summary['class_name'] ?? '-' }}</span>
                            <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ ($summary['is_eligible'] ?? false) ? 'bg-secondary-container text-on-secondary-container' : 'bg-error-container text-on-error-container' }}"><span class="material-symbols-outlined text-[15px]">{{ ($summary['is_eligible'] ?? false) ? 'check_circle' : 'cancel' }}</span>{{ ($summary['is_eligible'] ?? false) ? 'Memenuhi' : 'Belum Memenuhi' }}</span>
                        </div>
                        <div class="mt-4"><div class="mb-1.5 flex items-center justify-between text-xs"><span class="font-medium text-on-surface">Progres</span><span class="font-numeric-data font-semibold text-on-surface">{{ number_format($summary['progress_percentage'] ?? 0, 0) }}%</span></div><div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-container-high" role="progressbar" aria-valuenow="{{ $summary['progress_percentage'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-primary" style="width: {{ $summary['progress_percentage'] ?? 0 }}%"></div></div><p class="mt-1.5 text-xs text-on-surface-variant">{{ $summary['satisfied_count'] ?? 0 }}/{{ $summary['applicable_count'] ?? 0 }} terpenuhi</p></div>
                        <button type="button" wire:click="showDetail({{ $student->id }})" wire:loading.attr="disabled" wire:target="showDetail({{ $student->id }})" class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-primary/50 text-sm font-semibold text-primary transition-colors hover:border-primary hover:bg-primary-fixed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-wait disabled:opacity-60"><span class="material-symbols-outlined text-[17px]">visibility</span>Detail</button>
                    </article>
                @empty
                    <div class="px-5 py-10 text-center"><span class="material-symbols-outlined mb-2 text-3xl text-outline">person_search</span><p class="text-sm font-medium text-on-surface">Belum ada siswa yang cocok dengan filter ini.</p><p class="mt-1 text-xs leading-5 text-on-surface-variant">Coba ubah jenjang, kelas, status, atau kata pencarian.</p></div>
                @endforelse
            </div>

            @if ($students->total() > 0)
                <footer class="flex flex-col gap-3 border-t border-outline-variant bg-surface-container-low/40 px-4 py-3.5 sm:px-5 md:flex-row md:items-center md:justify-between">
                    <p class="text-center text-xs text-on-surface-variant md:text-left">Menampilkan <span class="font-medium text-on-surface">{{ $students->firstItem() }}–{{ $students->lastItem() }}</span> dari <span class="font-medium text-on-surface">{{ number_format($students->total(), 0, ',', '.') }}</span> siswa</p>
                    @if ($students->hasPages())
                        <div class="min-w-0 overflow-x-auto">{{ $students->onEachSide(1)->links() }}</div>
                    @endif
                </footer>
            @endif

            <div wire:loading.flex wire:target="search,filterLevel,filterClassId,filterStatus,resetFilters" class="absolute inset-0 z-20 items-center justify-center bg-surface-container-lowest/70 backdrop-blur-[1px]" aria-live="polite"><div class="inline-flex items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm font-medium text-on-surface shadow-sm"><span class="h-4 w-4 animate-spin rounded-full border-2 border-primary/30 border-t-primary"></span>Memuat data</div></div>
        </section>
        @endif
    @endif

    @if ($isRequirementModalOpen && $academicYear)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3 md:p-6" wire:click.self="closeRequirementModal">
            <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
                <div class="flex items-center justify-between border-b border-outline-variant px-5 py-4 md:px-7"><div><h2 class="text-headline-sm text-on-surface">Kriteria Kelayakan {{ $requirementLevel }}</h2><p class="text-body-sm text-on-surface-variant">Perubahan hanya berlaku untuk jenjang ini.</p></div><button type="button" wire:click="closeRequirementModal" aria-label="Tutup modal kriteria" class="rounded-full p-2 hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"><span class="material-symbols-outlined">close</span></button></div>
                <form wire:submit="saveRequirements" class="flex min-h-0 flex-1 flex-col">
                    <div class="overflow-y-auto p-5 md:p-7">
                        <div class="mb-6 grid gap-3 sm:grid-cols-4">
                            @foreach ($levels as $level)
                                <button type="button" wire:click="openRequirements('{{ $level->value }}')" class="rounded-xl border px-4 py-3 text-label-lg {{ $requirementLevel === $level->value ? 'border-primary bg-primary-fixed text-primary' : 'border-outline-variant text-on-surface' }}">{{ $level->value }}</button>
                            @endforeach
                        </div>

                        @foreach ([\App\Enums\BillFrequency::Monthly->value => 'Bulanan', \App\Enums\BillFrequency::Yearly->value => 'Tahunan', \App\Enums\BillFrequency::OneTime->value => 'Satu Kali'] as $frequency => $frequencyLabel)
                            @php
                                $options = $requirementOptions->get($frequency, collect());
                                $pooledGroup = $pooledRequirementGroups->get($frequency);
                                $pooledMemberIds = $pooledGroup?->pooledPaymentTypes->pluck('id') ?? collect();
                                $individualOptions = $options->reject(fn ($paymentType) => $pooledMemberIds->contains($paymentType->id));
                            @endphp
                            <section class="mb-5 overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest">
                                <div class="border-b border-outline-variant bg-surface-container-low px-4 py-3.5 sm:px-5"><h3 class="text-title-md text-on-surface">{{ $frequencyLabel }}</h3><p class="text-body-sm text-on-surface-variant">Pilih jenis pembayaran dan persentase minimum yang wajib terpenuhi.</p></div>
                                @if ($frequency === \App\Enums\BillFrequency::Yearly->value)
                                    <div class="grid gap-3 border-b border-outline-variant p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-5">
                                        <label>
                                            <span class="mb-1 block text-label-md">Tahun Ajaran</span>
                                            <select wire:model="yearlyAcademicYearId" class="w-full rounded-xl border-outline-variant focus:border-primary focus:ring-primary">
                                                @foreach ($academicYears as $yearOption)
                                                    <option value="{{ $yearOption->id }}">{{ $yearOption->year }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <p class="text-body-sm text-on-surface-variant">Hasil kelayakan dihitung dari tagihan tahun ajaran terpilih.</p>
                                    </div>
                                @endif
                                @if ($frequency === \App\Enums\BillFrequency::Monthly->value)
                                    <div class="grid gap-4 border-b border-outline-variant p-4 sm:grid-cols-2 sm:p-5">
                                        <label><span class="mb-1 block text-label-md">Mulai bulan</span><input type="month" wire:model="monthlyStart" class="w-full rounded-xl border-outline-variant focus:border-primary focus:ring-primary">@error('monthlyStart')<span class="text-body-sm text-error">{{ $message }}</span>@enderror</label>
                                        <label><span class="mb-1 block text-label-md">Sampai bulan</span><input type="month" wire:model="monthlyEnd" class="w-full rounded-xl border-outline-variant focus:border-primary focus:ring-primary">@error('monthlyEnd')<span class="text-body-sm text-error">{{ $message }}</span>@enderror</label>
                                    </div>
                                @endif
                                <div class="divide-y divide-outline-variant">
                                    @foreach ($individualOptions as $paymentType)
                                        <div wire:key="requirement-option-{{ $requirementLevel }}-{{ $frequency }}-{{ $paymentType->id }}" class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_105px] sm:items-center sm:px-5">
                                            <label class="flex items-center gap-3"><input type="checkbox" wire:model="selectedRequirements.{{ $frequency }}.{{ $paymentType->id }}" class="rounded border-outline text-primary focus:ring-primary"><span class="text-body-md text-on-surface">{{ $paymentType->name }}</span></label>
                                            <label class="relative"><span class="sr-only">Persentase minimum {{ $paymentType->name }}</span><input type="number" min="0.01" max="100" step="0.01" wire:model="requirementPercentages.{{ $frequency }}.{{ $paymentType->id }}" class="w-full rounded-lg border-outline-variant py-2 pl-3 pr-8 text-right font-numeric-data focus:border-primary focus:ring-primary"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant">%</span>@error("requirementPercentages.{$frequency}.{$paymentType->id}")<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror</label>
                                        </div>
                                    @endforeach
                                    @if ($individualOptions->isEmpty() && ! $pooledGroup)
                                        <p class="px-5 py-5 text-body-sm text-on-surface-variant">Belum ada jenis pembayaran aktif dengan tarif {{ strtolower($frequencyLabel) }} untuk jenjang ini.</p>
                                    @endif
                                </div>
                                @if ($pooledGroup && isset($pooledRequirements[$frequency]))
                                    @php
                                        $pool = $pooledRequirements[$frequency];
                                        $pooledMemberNames = $pooledGroup->pooledPaymentTypes->sortBy('name')->pluck('name')->implode(' + ');
                                    @endphp
                                    <div class="border-t border-outline-variant bg-surface-container-low/30 p-4 sm:p-5">
                                        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
                                            <div class="flex flex-col gap-3 border-b border-outline-variant bg-surface-container-low px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2"><span class="material-symbols-outlined text-[19px] text-on-surface">join_inner</span><h4 class="font-semibold text-on-surface">Syarat Gabungan</h4></div>
                                                </div>
                                                <label class="inline-flex shrink-0 items-center gap-2 text-sm font-medium text-on-surface"><input type="checkbox" wire:model.live="pooledRequirements.{{ $frequency }}.enabled" @checked($pool['enabled']) class="rounded border-outline text-primary focus:ring-primary">Aktif</label>
                                            </div>
                                            <div class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_180px] sm:items-end">
                                                <div class="min-w-0"><p class="break-words font-medium text-on-surface">{{ $pooledMemberNames }}</p><p class="mt-1 text-body-sm text-on-surface-variant">Pembayaran seluruh anggota dihitung sebagai satu syarat.</p></div>
                                                <label class="block"><span class="mb-2 block text-label-md text-on-surface">Minimum pembayaran gabungan</span><span class="relative block"><input type="number" min="0.01" max="100" step="0.01" value="{{ $pool['percentage'] }}" wire:model="pooledRequirements.{{ $frequency }}.percentage" class="w-full rounded-lg border-outline-variant py-2 pl-3 pr-8 text-right font-numeric-data focus:border-primary focus:ring-primary"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant">%</span></span>@error("pooledRequirements.{$frequency}.percentage")<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror</label>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap justify-end gap-3 border-t border-outline-variant bg-surface-container-lowest px-5 py-4 md:px-7"><button type="button" wire:click="closeRequirementModal" class="rounded-xl border border-outline-variant px-5 py-2.5 text-label-lg">Batal</button><button type="button" wire:click="openResetConfirmation('{{ $requirementLevel }}')" class="rounded-xl border border-error/40 px-5 py-2.5 text-label-lg text-error hover:bg-error/5">Reset Kriteria</button><button type="submit" wire:loading.attr="disabled" wire:target="saveRequirements" class="rounded-xl bg-primary px-5 py-2.5 text-label-lg text-on-primary disabled:cursor-wait disabled:opacity-60"><span wire:loading.remove wire:target="saveRequirements">Simpan &amp; Terapkan</span><span wire:loading wire:target="saveRequirements">Menyimpan...</span></button></div>
                </form>
            </div>
        </div>
    @endif

    @if ($isDetailModalOpen && $detail)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3 md:p-6" wire:click.self="closeDetailModal">
            <div class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
                <div class="flex items-start justify-between border-b border-outline-variant px-5 py-4 md:px-7"><div><p class="text-label-md uppercase tracking-wider text-primary">Detail Kelayakan</p><h2 class="text-headline-sm text-on-surface">{{ $detail['student_name'] }}</h2><p class="text-body-sm text-on-surface-variant">{{ $detail['school_level'] }} / {{ $detail['class_name'] }} · {{ $detail['exam_name'] }} · Tahun Ajaran {{ $detail['academic_year'] }}</p></div><button type="button" wire:click="closeDetailModal" aria-label="Tutup detail kelayakan" class="rounded-full p-2 hover:bg-surface-container-low focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"><span class="material-symbols-outlined">close</span></button></div>
                <div class="overflow-y-auto p-5 md:p-7">
                    <div class="mb-6 grid gap-4 md:grid-cols-[220px_1fr]">
                        <div class="rounded-2xl p-5 {{ $detail['is_eligible'] ? 'bg-secondary-container text-on-secondary-container' : 'bg-error-container text-on-error-container' }}"><span class="material-symbols-outlined text-3xl">{{ $detail['is_eligible'] ? 'verified' : 'pending_actions' }}</span><p class="mt-3 text-title-lg">{{ $detail['is_eligible'] ? 'Memenuhi' : 'Belum Memenuhi' }}</p><p class="text-body-sm">{{ $detail['satisfied_count'] }}/{{ $detail['applicable_count'] }} kewajiban terpenuhi</p></div>
                        @if (! $detail['is_eligible'] && $detail['unmet_reasons'])
                            <div class="rounded-2xl border border-error/30 bg-error-container/30 p-5"><h3 class="mb-2 text-label-lg text-error">Yang perlu diselesaikan</h3><ul class="space-y-2 text-body-sm text-on-surface">@foreach ($detail['unmet_reasons'] as $reason)<li class="flex gap-2"><span class="material-symbols-outlined text-[18px] text-error">arrow_right</span><span>{{ $reason }}</span></li>@endforeach</ul></div>
                        @else
                            <div class="rounded-2xl border border-outline-variant p-5"><h3 class="text-label-lg text-on-surface">Semua persyaratan terpenuhi</h3><p class="mt-1 text-body-sm text-on-surface-variant">Perhitungan memakai alokasi pembayaran aktif pada StudentBill terkait.</p></div>
                        @endif
                    </div>

                    @if ($detail['monthly_periods'])
                        <section class="mb-6 overflow-hidden rounded-2xl border border-outline-variant"><div class="border-b border-outline-variant bg-surface-container-low px-5 py-4"><h3 class="text-title-md">Matriks Bulanan</h3><p class="text-body-sm text-on-surface-variant">Setiap bulan dinilai mandiri; kelebihan bulan lain tidak dipindahkan.</p></div><div class="overflow-x-auto"><table class="min-w-max text-left"><thead class="text-label-sm text-on-surface-variant"><tr><th class="sticky left-0 z-10 min-w-48 bg-surface-container-lowest px-4 py-3">Jenis Pembayaran</th>@foreach ($detail['monthly_periods'] as $period)<th class="min-w-36 px-4 py-3 text-center">{{ $period['label'] }}</th>@endforeach</tr></thead><tbody class="divide-y divide-outline-variant">@foreach ($detail['monthly_rows'] as $row)<tr><td class="sticky left-0 bg-surface-container-lowest px-4 py-3 font-label-md">{{ $row['payment_type_name'] }}</td>@foreach ($detail['monthly_periods'] as $period)@php $cell = $row['periods'][$period['key']] ?? null; @endphp<td class="px-4 py-3 text-center">@if ($cell)<span class="inline-flex rounded-full px-2.5 py-1 text-label-sm {{ $cell['status'] === 'pass' ? 'bg-secondary-container text-on-secondary-container' : ($cell['status'] === 'not_applicable' ? 'bg-surface-container-high text-on-surface-variant' : 'bg-error-container text-on-error-container') }}">{{ $cell['status_label'] }}</span><p class="mt-1 font-numeric-data text-body-sm">{{ number_format($cell['percentage'], 2, ',', '.') }}%</p>@else<span class="text-outline">-</span>@endif</td>@endforeach</tr>@endforeach</tbody></table></div></section>
                    @endif

                    @if ($detail['non_monthly_requirements'])
                        <section class="overflow-hidden rounded-2xl border border-outline-variant"><div class="border-b border-outline-variant bg-surface-container-low px-5 py-4"><h3 class="text-title-md">Tahunan dan Satu Kali</h3></div><div class="divide-y divide-outline-variant">@foreach ($detail['non_monthly_requirements'] as $item)<div class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto_auto] sm:items-center"><div>@if ($item['is_pooled'])<p class="text-xs font-semibold uppercase tracking-wide text-primary">Syarat Gabungan</p>@endif<p class="font-label-lg">{{ $item['payment_type_name'] }}</p><p class="text-body-sm text-on-surface-variant">Target Rp {{ number_format($item['target'], 2, ',', '.') }} · Dibayar Rp {{ number_format($item['paid'], 2, ',', '.') }} · Minimum Rp {{ number_format($item['required_amount'], 2, ',', '.') }}</p></div><p class="font-numeric-data text-label-lg">{{ number_format($item['percentage'], 2, ',', '.') }}%</p><span class="w-fit rounded-full px-3 py-1 text-label-sm {{ $item['status'] === 'pass' ? 'bg-secondary-container text-on-secondary-container' : ($item['status'] === 'not_applicable' ? 'bg-surface-container-high text-on-surface-variant' : 'bg-error-container text-on-error-container') }}">{{ $item['status_label'] }}</span></div>@endforeach</div></section>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($isResetConfirmOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-3 md:p-6" wire:click.self="cancelReset">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
                <div class="flex items-start gap-4 p-5 sm:p-6">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-error-container text-error"><span class="material-symbols-outlined text-[26px]">warning</span></div>
                    <div class="min-w-0">
                        <h2 class="text-headline-sm text-on-surface">Reset Kriteria {{ $resetLevel }}?</h2>
                        <p class="mt-1.5 text-body-sm leading-6 text-on-surface-variant">Kriteria kelayakan jenjang {{ $resetLevel }} akan dikembalikan ke pengaturan default. Data tagihan dan pembayaran siswa tidak akan diubah.</p>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-3 border-t border-outline-variant bg-surface-container-lowest px-5 py-4">
                    <button type="button" wire:click="cancelReset" class="rounded-xl border border-outline-variant px-5 py-2.5 text-label-lg">Batal</button>
                    <button type="button" wire:click="confirmReset" wire:loading.attr="disabled" wire:target="confirmReset" class="inline-flex items-center gap-2 rounded-xl bg-error px-5 py-2.5 text-label-lg text-on-error disabled:cursor-wait disabled:opacity-60"><span wire:loading.remove wire:target="confirmReset">Ya, Reset Kriteria</span><span wire:loading wire:target="confirmReset">Mereset...</span></button>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        const savedExamFilters = localStorage.getItem('annur.examEligibility.filters');

        if (savedExamFilters) {
            try {
                $wire.restoreExamFilters(JSON.parse(savedExamFilters));
            } catch (error) {
                localStorage.removeItem('annur.examEligibility.filters');
            }
        }
    </script>
    @endscript
</div>
