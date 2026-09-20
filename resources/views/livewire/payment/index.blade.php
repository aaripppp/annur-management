<div>
    <div class="mb-stack-lg">
        <h1 class="text-display-sm font-display-sm text-on-surface">Pembayaran</h1>
        <p class="text-body-md text-on-surface-variant mt-1">Kelola tagihan dan pembayaran siswa.</p>
    </div>

    @if(session()->has('success'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
            class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]"
        >
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <div class="border-b border-outline-variant mb-stack-lg">
        <nav class="flex items-center gap-1 overflow-x-auto" aria-label="Bagian pembayaran">
            <button
                type="button"
                wire:click="setActiveTab('student')"
                class="relative px-4 py-3 text-label-lg font-label-lg whitespace-nowrap transition-colors {{ $activeTab === 'student' ? 'text-primary' : 'text-on-surface-variant hover:text-on-surface' }}"
                aria-current="{{ $activeTab === 'student' ? 'page' : 'false' }}"
            >
                Pembayaran Siswa
                @if($activeTab === 'student')
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

    @if($activeTab === 'student')
    {{-- Student payment summary + date filter --}}
    <section class="mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl px-5 py-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-headline-sm font-headline-sm text-on-surface">Ringkasan Pemasukan Siswa</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Total pemasukan dan jumlah transaksi pembayaran siswa.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="block text-label-sm font-label-sm text-on-surface-variant" for="student-summary-preset">
                    PERIODE
                    <select id="student-summary-preset" wire:model.live="summaryPreset" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                        <option value="all">Semua Hari</option>
                        <option value="today">Hari Ini</option>
                        <option value="yesterday">Kemarin</option>
                        <option value="this_month">Bulan Ini</option>
                    </select>
                </label>
                <label class="block text-label-sm font-label-sm text-on-surface-variant" for="student-summary-start-date">
                    TANGGAL MULAI
                    <input id="student-summary-start-date" type="date" wire:model.live="summaryStartDate" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
                    @error('summaryStartDate')<span class="mt-1 block text-xs text-error">{{ $message }}</span>@enderror
                </label>
                <label class="block text-label-sm font-label-sm text-on-surface-variant" for="student-summary-end-date">
                    TANGGAL AKHIR
                    <input id="student-summary-end-date" type="date" wire:model.live="summaryEndDate" class="mt-1 h-10 w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 text-body-sm text-on-surface shadow-sm focus:border-primary focus:ring-primary">
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
                    <p class="text-on-surface-variant text-body-md font-body-md">Total Pemasukan Siswa</p>
                    <p data-testid="student-summary-total-amount" class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($summaryTotal, 0, ',', '.') }}</p>
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
                    <p data-testid="student-summary-total-count" class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($summaryCount, 0, ',', '.') }} Transaksi</p>
                </div>
            </div>
        </div>
    </section>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm mb-stack-lg">
        <label for="student-search" class="block text-label-md font-label-md text-on-surface mb-1">Cari Siswa / Calon Siswa</label>
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
            <input
                id="student-search"
                type="text"
                wire:model.live.debounce.300ms="studentSearch"
                placeholder="Cari nama lengkap, nama panggilan, NIS, atau nomor pendaftaran..."
                autocomplete="off"
                class="w-full pl-10 pr-4 py-2.5 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm"
            >
        </div>

        @if($selectedStudent)
            <p class="text-body-sm text-on-surface-variant mt-2">Gunakan tombol Ganti Siswa pada profil untuk memilih siswa lain.</p>
        @elseif(mb_strlen(trim($studentSearch)) >= 2)
            @if($searchResults->isNotEmpty() || $prospectiveSearchResults->isNotEmpty())
                <div class="mt-3 border border-outline-variant rounded-xl overflow-hidden divide-y divide-outline-variant shadow-sm">
                    @foreach($searchResults as $student)
                        @php($academicStatus = $student->academicStatus())
                        <button
                            type="button"
                            wire:key="student-result-{{ $student->id }}"
                            wire:click="selectStudent({{ $student->id }})"
                            class="w-full px-4 py-3 text-left hover:bg-surface-container-low transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold text-on-surface text-body-md truncate">{{ $student->nama_lengkap }}</p>
                                <p class="text-body-sm text-on-surface-variant mt-0.5">
                                    NIS: {{ $student->nis ?? '—' }} &bull; {{ $student->academicClassLabel() }} &bull; {{ $student->schoolLevel?->value ?? '-' }} &bull; {{ $student->academicYearContextLabel() ?? '-' }}
                                </p>
                            </div>

                            @if($academicStatus === 'lulus')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-on-tertiary-fixed whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[14px]">school</span>
                                    {{ $student->academic_status_label }}
                                </span>
                            @elseif($academicStatus === 'calon_siswa')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[14px]">person_add</span>
                                    {{ $student->academic_status_label }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-secondary-fixed text-on-secondary-fixed whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                    {{ $student->academic_status_label }}
                                </span>
                            @endif
                        </button>
                    @endforeach

                    @if($prospectiveSearchResults->isNotEmpty())
                        <div class="flex items-center gap-2 px-4 py-2 bg-surface-container-low text-label-md font-label-md text-primary uppercase tracking-wider">
                            <span class="material-symbols-outlined text-[18px]">person_add</span>
                            Calon Siswa
                        </div>
                        @foreach($prospectiveSearchResults as $prospectiveStudent)
                            <a
                                href="{{ route('pembayaran.prospective.workspace', $prospectiveStudent) }}"
                                wire:navigate
                                wire:key="prospective-result-{{ $prospectiveStudent->id }}"
                                class="w-full px-4 py-3 text-left hover:bg-surface-container-low transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p class="font-semibold text-on-surface text-body-md truncate">{{ $prospectiveStudent->nama_lengkap }}</p>
                                    <p class="text-body-sm text-on-surface-variant mt-0.5 font-numeric-data">
                                        {{ $prospectiveStudent->registration_number }} &bull; {{ $prospectiveStudent->schoolClass?->name ?? 'Kelas —' }} &bull; TA {{ $prospectiveStudent->academicYear?->year ?? '—' }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[14px]">person_add</span>
                                    Calon Siswa
                                </span>
                            </a>
                        @endforeach
                    @endif
                </div>
            @else
                <div class="mt-3 border border-dashed border-outline-variant rounded-xl px-5 py-6 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-3xl mb-1 block">person_search</span>
                    <p class="text-body-md">Siswa atau calon siswa tidak ditemukan.</p>
                </div>
            @endif
        @else
            <div class="mt-5 border border-dashed border-outline-variant rounded-xl px-6 py-8 text-center text-on-surface-variant">
                <span class="material-symbols-outlined text-4xl mb-2 block">person_search</span>
                <p class="text-body-md">Cari siswa atau calon siswa untuk melihat data dan input pembayaran.</p>
            </div>
        @endif
    </div>

    @if($selectedStudent)
        @php($academicStatus = $selectedStudent->academicStatus())
        <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl px-5 py-5 sm:px-6 shadow-sm mb-stack-lg">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 lg:gap-6">
                <div class="flex items-center gap-4 min-w-0">
                    @if($selectedStudent->foto)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($selectedStudent->foto) }}" alt="Foto {{ $selectedStudent->nama_lengkap }}" class="w-14 h-14 rounded-full object-cover border border-outline-variant shrink-0">
                    @else
                        <div class="w-14 h-14 rounded-full bg-surface-container-high border border-outline-variant text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[28px]">person</span>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-headline-md font-headline-md text-on-surface leading-tight">
                                <span class="sr-only">Profil Siswa: </span>{{ $selectedStudent->nama_lengkap }}
                            </h2>
                            @if($academicStatus === 'lulus')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-on-tertiary-fixed whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[14px]">school</span>
                                    {{ $selectedStudent->academic_status_label }}
                                </span>
                            @elseif($academicStatus === 'calon_siswa')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[14px]">person_add</span>
                                    {{ $selectedStudent->academic_status_label }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-secondary-fixed text-on-secondary-fixed whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                    {{ $selectedStudent->academic_status_label }}
                                </span>
                            @endif
                        </div>
                        <p class="text-body-sm text-on-surface-variant mt-1.5">
                            <span class="font-numeric-data">NIS {{ $selectedStudent->nis ?? '—' }}</span>
                            <span class="mx-1">&bull;</span>
                            {{ $selectedStudent->nama_panggilan ?? '—' }}
                            <span class="mx-1">&bull;</span>
                            {{ $selectedStudent->jenis_kelamin === 'L' ? 'Laki-laki' : ($selectedStudent->jenis_kelamin === 'P' ? 'Perempuan' : '—') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0 lg:justify-end">
                    <button
                        type="button"
                        wire:click="changeStudent"
                        class="px-3.5 py-2 text-on-surface-variant font-label-md border border-outline-variant rounded-xl hover:bg-surface-container transition-colors flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[18px]">person_search</span>
                        Ganti Siswa
                    </button>
                    <button
                        type="button"
                        wire:click="openEditProfile"
                        class="px-3.5 py-2 text-primary font-label-md border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                        Edit Profil
                    </button>
                    <a
                        href="{{ route('pembayaran.create', ['student' => $selectedStudent->id]) }}"
                        class="px-4 py-2 bg-primary hover:bg-primary/90 text-on-primary font-label-md rounded-xl transition-colors shadow-sm flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[20px]">add</span>
                        Input Pembayaran
                    </a>
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-outline-variant">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4">
                    <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="class">
                        <div class="w-9 h-9 rounded-lg bg-primary-fixed/60 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[19px]">school</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Kelas</p>
                            <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $selectedStudent->academicClassLabel() }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:px-6" data-profile-field="academic-year">
                        <div class="w-9 h-9 rounded-lg bg-primary-fixed/40 text-primary flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[19px]">calendar_month</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tahun Ajaran</p>
                            <p class="text-body-md font-semibold text-on-surface mt-0.5 font-numeric-data">{{ $academicYearContext ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:pl-6" data-profile-field="birth">
                        <div class="w-9 h-9 rounded-lg bg-secondary-container/60 text-secondary flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[19px]">cake</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tempat, Tanggal Lahir</p>
                            <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ collect([$selectedStudent->tempat_lahir, $selectedStudent->tanggal_lahir ? $selectedStudent->tanggal_lahir->locale('id')->translatedFormat('d F Y') : null])->filter()->implode(', ') ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4 mt-4 pt-4 border-t border-outline-variant/60">
                    <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="address">
                        <div class="w-9 h-9 rounded-lg bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[19px]">location_on</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Alamat</p>
                            <p class="text-body-md font-semibold text-on-surface mt-0.5 whitespace-pre-line break-words">{{ $selectedStudent->alamat ?: '-' }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:px-6" data-profile-field="father">
                        <div class="w-9 h-9 rounded-lg bg-tertiary-fixed/60 text-on-tertiary-fixed flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[19px]">person</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Ayah</p>
                            @if($selectedStudent->nama_ayah && $selectedStudent->no_telp_ayah)
                                <p class="text-body-md text-on-surface mt-0.5 break-words" data-parent-value="father">
                                    <span class="font-semibold">{{ $selectedStudent->nama_ayah }}</span>
                                    <span class="mx-1 text-on-surface-variant">&bull;</span>
                                    <span class="text-on-surface-variant font-numeric-data">{{ $selectedStudent->no_telp_ayah }}</span>
                                </p>
                            @elseif($selectedStudent->nama_ayah)
                                <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $selectedStudent->nama_ayah }}</p>
                            @elseif($selectedStudent->no_telp_ayah)
                                <p class="text-body-md font-medium text-on-surface-variant mt-0.5 font-numeric-data">{{ $selectedStudent->no_telp_ayah }}</p>
                            @else
                                <p class="text-body-md font-semibold text-on-surface mt-0.5">-</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:pl-6" data-profile-field="mother">
                        <div class="w-9 h-9 rounded-lg bg-error-container/60 text-error flex items-center justify-center shrink-0" aria-hidden="true">
                            <span class="material-symbols-outlined text-[19px]">person_2</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Ibu</p>
                            @if($selectedStudent->nama_ibu && $selectedStudent->no_telp_ibu)
                                <p class="text-body-md text-on-surface mt-0.5 break-words" data-parent-value="mother">
                                    <span class="font-semibold">{{ $selectedStudent->nama_ibu }}</span>
                                    <span class="mx-1 text-on-surface-variant">&bull;</span>
                                    <span class="text-on-surface-variant font-numeric-data">{{ $selectedStudent->no_telp_ibu }}</span>
                                </p>
                            @elseif($selectedStudent->nama_ibu)
                                <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $selectedStudent->nama_ibu }}</p>
                            @elseif($selectedStudent->no_telp_ibu)
                                <p class="text-body-md font-medium text-on-surface-variant mt-0.5 font-numeric-data">{{ $selectedStudent->no_telp_ibu }}</p>
                            @else
                                <p class="text-body-md font-semibold text-on-surface mt-0.5">-</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <x-student.registration-history-card :prospect="$registeredProspect" :summary="$registrationSummary" />

        @if($billbook)
            <section>
                <div class="mb-4">
                    <h2 class="text-headline-md font-headline-md text-on-surface">Billbook Siswa</h2>
                    <p class="text-body-md text-on-surface-variant mt-1">Ringkasan dan rincian tagihan siswa berdasarkan tahun ajaran dan periode.</p>
                </div>

                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm mb-stack-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <label for="payment-academic-year" class="text-body-md font-body-md text-on-surface-variant whitespace-nowrap">Tahun Ajaran:</label>
                                <select id="payment-academic-year" wire:model.live="selectedAcademicYear" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[160px]">
                                    <option value="">Semua</option>
                                    @foreach($academicYearSelectorOptions as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                <label for="payment-summary-period" class="text-body-md font-body-md text-on-surface-variant whitespace-nowrap">Periode:</label>
                                <select id="payment-summary-period" wire:model.live="summaryPeriod" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[200px]">
                                    <option value="all">Semua Periode</option>
                                    @foreach($billbook['periodOptions'] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                    @foreach($billbook['academicYearOptions'] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                    @if($billbook['showOtherOption'])
                                        <option value="other">Tagihan Lainnya</option>
                                    @endif
                                </select>
                            </div>
                        </div>

                        @if($billbook['summaryPeriodEmpty'])
                            <p class="text-body-sm text-on-surface-variant">Belum ada tagihan pada periode ini</p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-stack-lg">
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
                        <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined">receipt_long</span>
                        </div>
                        <div class="mt-2">
                            <p class="text-on-surface-variant text-body-md font-body-md">Total Tagihan</p>
                            <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($billbook['totalTagihan'], 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
                        <div class="w-10 h-10 rounded-full bg-tertiary-fixed flex items-center justify-center text-on-tertiary-fixed">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                        <div class="mt-2">
                            <p class="text-on-surface-variant text-body-md font-body-md">Total Dibayar</p>
                            <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">Rp {{ number_format($billbook['totalDibayar'], 0, ',', '.') }}</p>
                        </div>
                    </div>

                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
                        <div class="w-10 h-10 rounded-full bg-error-container flex items-center justify-center text-on-error-container">
                            <span class="material-symbols-outlined">priority_high</span>
                        </div>
                        <div class="mt-2">
                            <p class="text-on-surface-variant text-body-md font-body-md">Total Tunggakan</p>
                            <p class="text-headline-md font-headline-md text-error mt-1 font-numeric-data tracking-wider">Rp {{ number_format($billbook['totalTunggakan'], 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>

                @if($billbook['groupedMonthlyBills']->isEmpty() && $billbook['groupedYearlyBills']->isEmpty() && $billbook['oneTimeBills']->isEmpty())
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-stack-lg">
                        <div class="p-8 text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-4xl mb-2 block">receipt_long</span>
                            <p>Belum ada tagihan untuk siswa ini.</p>
                        </div>
                    </div>
                @else
                    @foreach($billbook['groupedMonthlyBills'] as $group)
                        <div wire:key="workspace-month-{{ $group['period_year'] }}-{{ $group['period_month'] }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-stack-lg">
                            <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-headline-sm font-headline-sm text-on-surface">{{ $group['title'] }}</h3>
                                        @include('livewire.student.period-status-badge', ['status' => $group['status']])
                                    </div>
                                    <p class="text-body-sm text-on-surface-variant mt-0.5">Tagihan Bulanan &bull; {{ $group['count'] }} tagihan &bull; Total sisa Rp {{ number_format($group['total_remaining'], 0, ',', '.') }}</p>
                                </div>
                                <button wire:click="openAddBillForMonth({{ $group['period_month'] }}, {{ $group['period_year'] }})" class="flex items-center gap-1.5 text-primary font-label-lg border border-primary/40 rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[18px]">add_box</span>
                                    Tambah Tagihan
                                </button>
                            </div>
                            @include('livewire.student.bill-table', ['bills' => $group['bills']])
                        </div>
                    @endforeach

                    @foreach($billbook['groupedYearlyBills'] as $group)
                        <div wire:key="workspace-year-{{ $group['academic_year'] }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-stack-lg">
                            <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Tahunan</h3>
                                        @include('livewire.student.period-status-badge', ['status' => $group['status']])
                                    </div>
                                    <p class="text-body-sm text-on-surface-variant mt-0.5">Tahun Ajaran {{ $group['academic_year'] }} &bull; {{ $group['count'] }} tagihan &bull; Total sisa Rp {{ number_format($group['total_remaining'], 0, ',', '.') }}</p>
                                </div>
                                <button wire:click="openAddBillForAcademicYear('{{ $group['academic_year'] }}')" class="flex items-center gap-1.5 text-primary font-label-lg border border-primary/40 rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[18px]">add_box</span>
                                    Tambah Tagihan
                                </button>
                            </div>
                            @include('livewire.student.bill-table', ['bills' => $group['bills']])
                        </div>
                    @endforeach

                    @if($billbook['oneTimeBills']->isNotEmpty())
                        <div wire:key="workspace-one-time" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-stack-lg">
                            <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Sekali Bayar</h3>
                                        @include('livewire.student.period-status-badge', ['status' => $billbook['oneTimeStatus']])
                                    </div>
                                    <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $billbook['oneTimeBills']->count() }} tagihan &bull; Total sisa Rp {{ number_format($billbook['oneTimeBills']->sum(fn ($bill) => (float) $bill->remaining_amount), 0, ',', '.') }}</p>
                                </div>
                                <button wire:click="openAddBillForOneTime" class="flex items-center gap-1.5 text-primary font-label-lg border border-primary/40 rounded-xl px-3.5 py-2 hover:bg-primary-fixed/50 transition-colors whitespace-nowrap">
                                    <span class="material-symbols-outlined text-[18px]">add_box</span>
                                    Tambah Tagihan
                                </button>
                            </div>
                            @include('livewire.student.bill-table', ['bills' => $billbook['oneTimeBills']])
                        </div>
                    @endif
                @endif
            </section>
            @include('livewire.student.bill-management-modals')
        @endif
    @endif

    @if($isEditProfileOpen && $selectedStudent)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm transition-all" aria-labelledby="edit-profile-title" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <div>
                        <h3 class="text-headline-sm font-headline-sm text-on-surface" id="edit-profile-title">Edit Profil Siswa</h3>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $selectedStudent->nama_lengkap }}</p>
                    </div>
                    <button type="button" wire:click="closeEditProfile" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <form wire:submit="saveProfile" class="flex flex-col gap-5">
                        <div class="flex items-center gap-3 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                            <div class="w-9 h-9 rounded-lg bg-secondary-fixed/60 text-secondary flex items-center justify-center shrink-0" aria-hidden="true">
                                <span class="material-symbols-outlined text-[19px]">calendar_month</span>
                            </div>
                            <div>
                                <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tahun Ajaran</p>
                                <p class="text-body-md font-semibold text-on-surface mt-0.5 font-numeric-data">{{ $academicYearContext ?? '—' }}</p>
                            </div>
                            <span class="ml-auto text-label-sm text-on-surface-variant whitespace-nowrap">Hanya dibaca</span>
                        </div>

                        <div>
                            <label for="payment-entry-date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Masuk</label>
                            <input type="date" id="payment-entry-date" wire:model="entry_date" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <p class="text-body-sm text-on-surface-variant mt-1">Kosongkan jika mengikuti tanggal data siswa dibuat.</p>
                            @error('entry_date') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="edit-nis" class="block text-label-md font-label-md text-on-surface mb-1">NIS</label>
                                <input type="text" id="edit-nis" wire:model="nis" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('nis') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="edit-class-id" class="block text-label-md font-label-md text-on-surface mb-1">Kelas <span class="text-error">*</span></label>
                                <select id="edit-class-id" wire:model="class_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach($classes as $schoolClass)
                                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                                    @endforeach
                                </select>
                                @error('class_id') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="edit-nama-lengkap" class="block text-label-md font-label-md text-on-surface mb-1">Nama Lengkap <span class="text-error">*</span></label>
                                <input type="text" id="edit-nama-lengkap" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required>
                                @error('nama_lengkap') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="edit-nama-panggilan" class="block text-label-md font-label-md text-on-surface mb-1">Nama Panggilan</label>
                                <input type="text" id="edit-nama-panggilan" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('nama_panggilan') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        @include('livewire.student.profile-fields', ['fieldPrefix' => 'payment-profile'])

                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-2">Jenis Kelamin</label>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" wire:model="jenis_kelamin" value="L" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Laki-laki (L)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" wire:model="jenis_kelamin" value="P" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Perempuan (P)</span>
                                </label>
                            </div>
                            @error('jenis_kelamin') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="edit-alamat" class="block text-label-md font-label-md text-on-surface mb-1">Alamat Lengkap</label>
                            <textarea id="edit-alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"></textarea>
                            @error('alamat') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </form>
                </div>

                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeEditProfile" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="saveProfile" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif
    @else
        <section>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm mb-stack-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                    <div class="md:col-span-2 xl:col-span-1">
                        <label for="history-search" class="block text-label-md font-label-md text-on-surface mb-1">Cari Transaksi</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                            <input id="history-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Nama, NIS, atau nomor kwitansi..." class="w-full pl-10 pr-4 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label for="history-bank" class="block text-label-md font-label-md text-on-surface mb-1">Bank</label>
                        <select id="history-bank" wire:model.live="bankId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                            <option value="">Semua Bank</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->optionLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="history-status" class="block text-label-md font-label-md text-on-surface mb-1">Status</label>
                        <select id="history-status" wire:model.live="status" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                    <div>
                        <label for="history-start-date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Mulai</label>
                        <input id="history-start-date" type="date" wire:model.live="startDate" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                    </div>
                    <div>
                        <label for="history-end-date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Akhir</label>
                        <input id="history-end-date" type="date" wire:model.live="endDate" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                    </div>
                </div>
            </div>

            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="p-5 border-b border-outline-variant">
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Transaksi</h2>
                    <p class="text-body-sm text-on-surface-variant mt-1">Daftar seluruh transaksi pembayaran siswa.</p>
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
                            @forelse($payments as $row)
                                <tr wire:key="history-{{ $row->id }}" class="hover:bg-surface-container-low transition-colors">
                                    <td class="py-4 px-3 text-body-md text-on-surface-variant text-center whitespace-nowrap font-numeric-data">{{ ($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration }}</td>
                                    <td class="py-3 px-4 text-body-md font-bold text-on-surface font-numeric-data">
                                        <div class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                            <span class="whitespace-nowrap">{{ $row->receiptNumber }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $row->badgeClasses }}">{{ $row->badgeLabel }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-body-md text-on-surface-variant whitespace-nowrap">{{ $row->paymentDate?->translatedFormat('d M Y') ?? '—' }}</td>
                                    <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">
                                        <div class="font-semibold">{{ $row->name }}</div>
                                        <div class="text-body-sm text-on-surface-variant">{{ $row->secondaryInfo }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-body-md text-on-surface-variant max-w-[220px] truncate">{{ $row->detailDisplay }}</td>
                                    <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">
                                        <div class="font-semibold">{{ $row->bankName }}</div>
                                        @if($row->bankAccountNumber)
                                            <div class="text-body-sm text-on-surface-variant font-numeric-data">{{ $row->bankAccountNumber }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-body-md font-bold text-on-surface whitespace-nowrap font-numeric-data">Rp {{ number_format($row->totalAmount, 0, ',', '.') }}</td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @if($row->statusLabel === 'Dibatalkan')
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-error"></span>Dibatalkan</span>
                                        @elseif($row->statusLabel === 'Tunggakan')
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-error"></span>Tunggakan</span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span>{{ $row->statusLabel }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">{{ $row->creatorName }}</td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ $row->detailUrl }}" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Lihat Detail / Cetak Kwitansi"><span class="material-symbols-outlined text-[20px]">visibility</span></a>
                                            @if($row->isActive && $row->editUrl)
                                                <a href="{{ $row->editUrl }}" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit Pembayaran"><span class="material-symbols-outlined text-[20px]">edit</span></a>
                                            @endif
                                            @if($row->source === 'student')
                                                <button type="button" wire:click="confirmDelete({{ $row->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus Transaksi"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="p-8 text-center text-on-surface-variant">
                                        <span class="material-symbols-outlined text-4xl mb-2 block">receipt_long</span>
                                        <p>{{ trim($search) !== '' || $bankId !== '' || $status !== '' || $startDate !== '' || $endDate !== '' ? 'Tidak ada transaksi yang sesuai filter.' : 'Belum ada transaksi pembayaran.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($payments->hasPages())
                    <div class="p-4 border-t border-outline-variant">
                        {{ $payments->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>

            @if($isDeleteModalOpen && $deletingPayment)
                <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-payment-title">
                    <div class="absolute inset-0 bg-black/40" wire:click="cancelDelete"></div>
                    <div class="relative bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div class="px-6 py-5 border-b border-outline-variant flex items-start gap-4">
                            <div class="w-10 h-10 rounded-full bg-error-container flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-error text-[22px]">delete</span>
                            </div>
                            <div>
                                <h3 class="text-headline-sm font-headline-sm text-on-surface" id="delete-payment-title">Hapus transaksi ini secara permanen?</h3>
                                <p class="text-body-sm text-on-surface-variant mt-1">Pembayaran <strong class="font-numeric-data">{{ $deletingPayment->receipt_number }}</strong> untuk <strong>{{ $deletingPayment->student->nama_lengkap ?? '—' }}</strong> beserta detail transaksinya akan dihapus. {{ $deletingPayment->isManualPayment() ? 'Tindakan ini tidak mengubah tagihan siswa.' : 'Nilai pembayaran pada tagihan siswa akan dikembalikan.' }}</p>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-surface-container-low/60 border-t border-outline-variant flex justify-end gap-3">
                            <button type="button" wire:click="cancelDelete" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                            <button type="button" wire:click="delete" class="px-5 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                Hapus Permanen
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>
