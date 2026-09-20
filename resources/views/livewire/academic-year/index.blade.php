<div>
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Tahun Ajaran</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola tahun ajaran dan proses kenaikan kelas siswa.</p>
        </div>
        <button wire:click="openCreateForm" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Tahun Ajaran
        </button>
    </div>

    <!-- Toast -->
    @if (session()->has('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 3000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Summary Cards -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">calendar_today</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Tahun Ajaran Aktif</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 tracking-wider">{{ $activeYearLabel }}</p>
            </div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-tertiary-fixed flex items-center justify-center text-tertiary">
                    <span class="material-symbols-outlined">event_upcoming</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Tahun Ajaran Berikutnya</p>
                @if($newYearLabel)
                    <p class="text-headline-md font-headline-md text-on-surface mt-1 tracking-wider">{{ $newYearLabel }}</p>
                @else
                    <p class="text-title-md font-title-md text-on-surface-variant mt-1">Belum ada tahun ajaran berikutnya</p>
                @endif
            </div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">trending_up</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Aksi</p>
                @if($newYearLabel && !$isAlreadyProcessed)
                    <button wire:click="openPreview" class="text-headline-sm font-headline-sm text-primary mt-1 hover:underline transition-colors text-left">
                        Proses Kenaikan Kelas →
                    </button>
                @endif
                @if(!($newYearLabel && !$isAlreadyProcessed))
                    <p class="text-headline-sm font-headline-md text-on-surface-variant mt-1">-</p>
                @endif
            </div>
        </div>
    </section>

    <!-- Table -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Tahun Ajaran</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-2.5 px-3 w-12 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>
                        <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Tahun Ajaran</th>
                        <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Tanggal Mulai</th>
                        <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Tanggal Selesai</th>
                        <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                        <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center whitespace-nowrap">Siswa Terdaftar</th>
                        <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($academicYears as $year)
                        <tr wire:key="academic-year-{{ $year->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-3 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ $loop->iteration }}</td>
                            <td class="py-3 px-4 text-body-md font-body-md text-on-surface font-semibold whitespace-nowrap">{{ $year->year }}</td>
                            <td class="py-3 px-4 text-body-md text-on-surface-variant whitespace-nowrap">{{ $year->start_date->format('d M Y') }}</td>
                            <td class="py-3 px-4 text-body-md text-on-surface-variant whitespace-nowrap">{{ $year->end_date?->format('d M Y') ?? '-' }}</td>
                            <td class="py-3 px-4 text-center align-middle">
                                @if($year->is_active)
                                    <span class="inline-flex items-center justify-center py-0.5 px-2.5 rounded-full text-label-sm font-label-sm leading-5 whitespace-nowrap bg-secondary-container text-on-secondary-container">Aktif</span>
                                @else
                                    <span class="inline-flex items-center justify-center py-0.5 px-2.5 rounded-full text-label-sm font-label-sm leading-5 whitespace-nowrap bg-surface-container-high text-on-surface-variant">Tidak Aktif</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-body-md text-on-surface-variant text-center font-numeric-data whitespace-nowrap">
                                {{ $year->enrollments()->count() }} Siswa
                            </td>
                            <td class="py-3 px-4 text-right align-middle">
                                <button wire:click="openMonthlyPreview({{ $year->id }})" class="text-label-md font-label-md text-primary hover:underline transition-colors whitespace-nowrap">
                                    Generate Tagihan Bulanan
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">calendar_today</span>
                                <p>Belum ada tahun ajaran.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Form Modal -->
    @if($isCreateFormOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Tambah Tahun Ajaran Baru</h3>
                    <button wire:click="closeCreateForm" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="saveNewYear" class="flex flex-col gap-5">
                        <div>
                            <label for="newYearLabel" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran <span class="text-error">*</span></label>
                            <input type="text" id="newYearLabel" wire:model="newYearLabel" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: 2027/2028" maxlength="9">
                            @error('newYearLabel') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            <p class="text-body-sm text-on-surface-variant mt-1">Format: YYYY/YYYY (contoh: 2027/2028)</p>
                        </div>
                    </form>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeCreateForm" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="saveNewYear" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Already Processed Warning -->
    @if($isAlreadyProcessed)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-tertiary/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-tertiary" style="font-size: 32px;">info</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Sudah Diproses</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">{{ $processingMessage }}</p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="$set('isAlreadyProcessed', false)" class="flex-1 px-4 py-2.5 bg-secondary hover:bg-secondary/90 text-on-secondary font-label-lg rounded-xl transition-colors shadow-sm">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Preview Modal -->
    @if($isPreviewOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-5xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Preview Kenaikan Kelas</h3>
                    <button wire:click="closePreview" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <div class="bg-surface-container rounded-xl p-4 mb-4">
                        <p class="text-body-md text-on-surface-variant">Tahun Ajaran: <strong class="text-on-surface">{{ $activeYearLabel }} → {{ $newYearLabel }}</strong></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-primary-container rounded-xl p-4 text-center">
                            <p class="text-headline-lg font-headline-lg text-on-primary-container">{{ $previewTotalPromoted }}</p>
                            <p class="text-body-md text-on-primary-container">Naik Kelas</p>
                        </div>
                        <div class="bg-tertiary-container rounded-xl p-4 text-center">
                            <p class="text-headline-lg font-headline-lg text-on-tertiary-container">{{ $previewTotalGraduated }}</p>
                            <p class="text-body-md text-on-tertiary-container">Lulus</p>
                        </div>
                    </div>

                    @if($previewTotalBlocked > 0)
                        <div class="bg-error-container border border-error rounded-xl p-4 mb-4">
                            <p class="text-body-md text-on-error-container font-body-md flex items-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">error</span>
                                Promosi belum dapat diproses karena terdapat kelas sumber tanpa aturan aktif yang valid.
                            </p>
                            <ul class="mt-2 space-y-1 text-body-sm text-on-error-container">
                                @foreach($previewBlockedTerms as $blockedTerm)
                                    <li>{{ $blockedTerm }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @php
                        $classLevelLabels = [
                            -3 => 'KB',
                            -2 => 'TKA',
                            -1 => 'TKB',
                            1 => 'Kelas I',
                            2 => 'Kelas II',
                            3 => 'Kelas III',
                            4 => 'Kelas IV',
                            5 => 'Kelas V',
                            6 => 'Kelas VI',
                            7 => 'Kelas VII',
                            8 => 'Kelas VIII',
                            9 => 'Kelas IX',
                            10 => 'Kelas X',
                            11 => 'Kelas XI',
                            12 => 'Kelas XII',
                        ];
                        $jenjangLevels = [
                            'TK' => [-3, -2, -1],
                            'SD' => [1, 2, 3, 4, 5, 6],
                            'SMP' => [7, 8, 9],
                            'SMA' => [10, 11, 12],
                        ];
                        $formatRombels = function ($names): string {
                            $sortedNames = $names
                                ->filter()
                                ->unique()
                                ->sort(fn (string $left, string $right): int => strnatcasecmp($left, $right))
                                ->values();

                            return match ($sortedNames->count()) {
                                0 => '-',
                                1 => $sortedNames->first(),
                                default => $sortedNames->first().' - '.$sortedNames->last(),
                            };
                        };
                        $sortedPreview = collect($previewGrouped)
                            ->sort(function (array $left, array $right): int {
                                $levelComparison = ((int) ($left['current_class']['level'] ?? 0)) <=> ((int) ($right['current_class']['level'] ?? 0));

                                return $levelComparison !== 0
                                    ? $levelComparison
                                    : strnatcasecmp($left['current_class']['name'] ?? '', $right['current_class']['name'] ?? '');
                            })
                            ->values();
                        $previewSections = collect($jenjangLevels)->mapWithKeys(function (array $levels, string $jenjang) use ($classLevelLabels, $formatRombels, $sortedPreview): array {
                            $groups = $sortedPreview->filter(fn (array $group): bool => in_array((int) ($group['current_class']['level'] ?? 0), $levels, true));

                            if ($groups->isEmpty()) {
                                return [];
                            }

                            $rows = $groups
                                ->groupBy(fn (array $group): int => (int) $group['current_class']['level'])
                                ->map(function ($levelGroups, int $level) use ($classLevelLabels, $formatRombels): array {
                                    $targetClasses = $levelGroups->pluck('target_class')->filter();
                                    $firstTargetClass = $targetClasses->first();
                                    $isGraduation = $levelGroups->every(fn (array $group): bool => $group['is_graduation']);
                                    $targetLabels = $levelGroups->pluck('target_label')->filter()->unique()->values();

                                    return [
                                        'level' => $level,
                                        'source_label' => $classLevelLabels[$level] ?? (string) $level,
                                        'source_rombel' => $formatRombels($levelGroups->pluck('current_class.name')),
                                        'rombel_count' => $levelGroups->count(),
                                        'student_count' => $levelGroups->sum('count'),
                                        'is_graduation' => $isGraduation,
                                        'target_label' => $isGraduation
                                            ? ($targetLabels->first() ?? '-')
                                            : ($classLevelLabels[(int) ($firstTargetClass['level'] ?? 0)] ?? $targetLabels->implode(', ')),
                                        'target_rombel' => $isGraduation
                                            ? null
                                            : $formatRombels($targetClasses->pluck('name')),
                                    ];
                                })
                                ->sortBy('level')
                                ->values();

                            return [$jenjang => [
                                'rows' => $rows,
                                'tingkat_count' => $rows->count(),
                                'rombel_count' => $groups->count(),
                                'student_count' => $groups->sum('count'),
                            ]];
                        });
                    @endphp

                    <div class="space-y-3">
                        @forelse($previewSections as $jenjang => $section)
                            <section
                                class="border border-outline-variant rounded-xl overflow-hidden"
                                data-preview-jenjang="{{ $jenjang }}"
                                x-data="{ expanded: false }"
                            >
                                <button
                                    type="button"
                                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-surface-container-low transition-colors"
                                    x-on:click="expanded = ! expanded"
                                    x-bind:aria-expanded="expanded"
                                >
                                    <span class="w-9 h-9 shrink-0 rounded-lg bg-primary-container text-on-primary-container flex items-center justify-center">
                                        <span class="material-symbols-outlined" style="font-size: 20px;">school</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-title-lg font-title-lg text-on-surface">{{ $jenjang }}</span>
                                        <span class="block text-body-sm text-on-surface-variant">
                                            {{ $section['tingkat_count'] }} tingkat • {{ $section['rombel_count'] }} rombel • {{ $section['student_count'] }} siswa
                                        </span>
                                    </span>
                                    <span class="material-symbols-outlined text-on-surface-variant transition-transform" x-bind:class="expanded && 'rotate-180'">expand_more</span>
                                </button>

                                <div class="border-t border-outline-variant" x-show="expanded" x-cloak>
                                    <div class="hidden md:grid grid-cols-[minmax(0,2fr)_auto_auto_2rem_minmax(0,2fr)] gap-4 items-center px-4 py-2 bg-surface-container-low text-label-sm font-label-sm text-on-surface-variant">
                                        <span>Dari Kelas</span>
                                        <span>Jumlah Rombel</span>
                                        <span>Siswa</span>
                                        <span aria-hidden="true"></span>
                                        <span>Ke Kelas</span>
                                    </div>

                                    <div class="divide-y divide-outline-variant">
                                        @foreach($section['rows'] as $row)
                                            <div
                                                class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:grid-cols-[minmax(0,2fr)_auto_auto_2rem_minmax(0,2fr)] gap-x-3 gap-y-2 md:gap-4 items-center px-4 py-3"
                                                data-preview-level="{{ $row['level'] }}"
                                                data-rombel-count="{{ $row['rombel_count'] }}"
                                                data-student-count="{{ $row['student_count'] }}"
                                            >
                                                <div class="min-w-0 col-start-1 row-start-1 md:col-start-auto md:row-start-auto">
                                                    <p class="text-label-lg font-label-lg text-on-surface">{{ $row['source_label'] }}</p>
                                                    <p class="text-body-sm text-on-surface-variant break-words">{{ $row['source_rombel'] }}</p>
                                                </div>
                                                <p class="col-start-1 row-start-2 md:col-start-auto md:row-start-auto text-body-sm text-on-surface-variant whitespace-nowrap">
                                                    {{ $row['rombel_count'] }} rombel
                                                </p>
                                                <p class="col-start-3 row-start-2 md:col-start-auto md:row-start-auto text-body-sm text-on-surface-variant whitespace-nowrap text-right md:text-left">
                                                    {{ $row['student_count'] }} siswa
                                                </p>
                                                <span class="material-symbols-outlined col-start-2 row-start-1 md:col-start-auto md:row-start-auto text-on-surface-variant" style="font-size: 20px;">arrow_forward</span>
                                                <div class="min-w-0 col-start-3 row-start-1 md:col-start-auto md:row-start-auto" data-preview-target>
                                                    @if($row['is_graduation'])
                                                        <span class="inline-flex items-center py-1 px-3 rounded-full text-label-sm font-label-sm bg-tertiary-container text-on-tertiary-container">
                                                            {{ $row['target_label'] }}
                                                        </span>
                                                    @else
                                                        <p class="text-label-lg font-label-lg text-on-surface">{{ $row['target_label'] }}</p>
                                                        <p class="text-body-sm text-on-surface-variant break-words">{{ $row['target_rombel'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @empty
                            <p class="text-body-md text-on-surface-variant text-center py-4">Tidak ada siswa aktif untuk diproses.</p>
                        @endforelse
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closePreview" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="openConfirm" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm" @if($previewTotalPromoted == 0 && $previewTotalGraduated == 0 || $previewTotalBlocked > 0) disabled @endif>
                        Konfirmasi & Proses
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Confirm Modal -->
    @if($isConfirmOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-primary" style="font-size: 32px;">school</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Proses Kenaikan Kelas?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Anda akan memproses <strong>{{ $previewTotalPromoted }} siswa naik kelas</strong> dan <strong>{{ $previewTotalGraduated }} siswa lulus</strong> dari tahun ajaran {{ $activeYearLabel }} ke {{ $newYearLabel }}.
                    </p>
                    <p class="text-body-sm text-on-surface-variant mt-2">
                        Tindakan ini tidak dapat dibatalkan. Tagihan tahun ajaran lama tidak akan diubah.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="closeConfirm" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="executePromotion" class="flex-1 px-4 py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm">Ya, Proses</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Blocked Feedback Modal -->
    @if($isBlockedOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">block</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Promosi Diblokir</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">{{ $blockedMessage }}</p>
                    @if(count($blockedTerms) > 0)
                        <ul class="mt-3 w-full space-y-1 text-left">
                            @foreach($blockedTerms as $blockedTerm)
                                <li class="text-body-md text-on-surface-variant bg-surface-container-low rounded-lg px-3 py-2">{{ $blockedTerm }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="text-body-sm text-on-surface-variant mt-3">
                        Atur aturan kenaikan kelas untuk seluruh kelas sumber pada menu Aturan Kenaikan Kelas terlebih dahulu, lalu jalankan proses kenaikan kelas kembali.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="closeBlocked" class="flex-1 px-4 py-2.5 bg-secondary hover:bg-secondary/90 text-on-secondary font-label-lg rounded-xl transition-colors shadow-sm">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Monthly Bill Generation: Already Generated Warning -->
    @if($isMonthlyAlreadyGenerated)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-tertiary/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-tertiary" style="font-size: 32px;">info</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Sudah Lengkap</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">{{ $monthlyProcessingMessage }}</p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="closeMonthlyPreview" class="flex-1 px-4 py-2.5 bg-secondary hover:bg-secondary/90 text-on-secondary font-label-lg rounded-xl transition-colors shadow-sm">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Monthly Bill Generation: Preview Modal -->
    @if($isMonthlyPreviewOpen && !$isMonthlyAlreadyGenerated)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Preview Generate Tagihan Bulanan</h3>
                    <button wire:click="closeMonthlyPreview" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <div class="bg-surface-container rounded-xl p-4 mb-4">
                        <p class="text-body-md text-on-surface-variant">Tahun Ajaran: <strong class="text-on-surface">{{ $monthlyPreviewAcademicYear }}</strong></p>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div class="bg-primary-container rounded-xl p-4 text-center">
                            <p class="text-headline-lg font-headline-lg text-on-primary-container">{{ $monthlyPreviewEligibleStudents }}</p>
                            <p class="text-body-md text-on-primary-container">Siswa Aktif</p>
                        </div>
                        <div class="bg-secondary-container rounded-xl p-4 text-center">
                            <p class="text-headline-lg font-headline-lg text-on-secondary-container">{{ $monthlyPreviewWillCreate }}</p>
                            <p class="text-body-md text-on-secondary-container">Akan Dibuat</p>
                        </div>
                        <div class="bg-surface-container rounded-xl p-4 text-center">
                            <p class="text-headline-lg font-headline-lg text-on-surface">{{ $monthlyPreviewAlreadyExisting }}</p>
                            <p class="text-body-md text-on-surface-variant">Sudah Ada (Skip)</p>
                        </div>
                    </div>

                    @if(count($monthlyPreviewTariffs) > 0)
                    @php
                        // Grouping murni presentasi: service hanya mengirim level
                        // yang memiliki enrollment eligible untuk tahun ajaran
                        // yang dipreview — tidak ada pelengkapan KB-12 di sini.
                        $levelLabels = \App\Models\SchoolClass::levelLabels();
                        $groupedTariffs = collect($monthlyPreviewTariffs)
                            ->groupBy(fn ($tariff) => (int) $tariff['class_level'])
                            ->sortKeys(SORT_NUMERIC);
                    @endphp
                    <div class="mb-4">
                        <h4 class="text-title-md font-title-md text-on-surface mb-2">Tarif yang Akan Digunakan</h4>
                        <div class="space-y-3">
                            @foreach($groupedTariffs as $level => $levelTariffs)
                                @php
                                    $levelLabel = $levelLabels[$level] ?? (string) $level;
                                    $groupLabel = $level >= 1 ? 'Kelas '.$levelLabel : $levelLabel;
                                @endphp
                                <div class="border border-outline-variant rounded-xl overflow-hidden">
                                    <div class="bg-surface-container-low px-4 py-2 flex items-center justify-between gap-4">
                                        <span class="text-label-lg font-label-lg text-on-surface">{{ $groupLabel }}</span>
                                        <span class="text-label-sm font-label-sm text-on-surface-variant whitespace-nowrap">{{ count($levelTariffs) }} jenis</span>
                                    </div>
                                    <div class="divide-y divide-outline-variant bg-surface-container-lowest">
                                        @foreach($levelTariffs as $tariff)
                                            <div class="flex items-center justify-between gap-4 px-4 py-2 text-body-md">
                                                <span class="text-on-surface-variant truncate">{{ $tariff['payment_type'] }}</span>
                                                <span class="text-on-surface font-semibold font-numeric-data whitespace-nowrap">Rp {{ number_format($tariff['amount'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <p class="text-body-sm text-on-surface-variant flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">info</span>
                        Tagihan yang sudah ada tidak akan dibuat ulang atau ditimpa. Hanya tagihan baru yang dibuat menggunakan tarif master terkini.
                    </p>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeMonthlyPreview" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="openMonthlyConfirm" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm" @if($monthlyPreviewWillCreate == 0) disabled @endif>
                        Konfirmasi & Generate
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Monthly Bill Generation: Confirm Modal -->
    @if($isMonthlyConfirmOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-primary" style="font-size: 32px;">receipt_long</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Generate Tagihan Bulanan?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Anda akan membuat <strong>{{ $monthlyPreviewWillCreate }} tagihan bulanan baru</strong> untuk tahun ajaran <strong>{{ $monthlyPreviewAcademicYear }}</strong>.
                    </p>
                    @if($monthlyPreviewAlreadyExisting > 0)
                        <p class="text-body-sm text-on-surface-variant mt-2">
                            {{ $monthlyPreviewAlreadyExisting }} tagihan yang sudah ada akan dilewati.
                        </p>
                    @endif
                    <p class="text-body-sm text-on-surface-variant mt-2">
                        Tagihan menggunakan tarif master saat ini. Tindakan ini tidak mengubah tagihan yang sudah ada.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="closeMonthlyConfirm" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="executeMonthlyGeneration" class="flex-1 px-4 py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm">Ya, Generate</button>
                </div>
            </div>
        </div>
    @endif
</div>
