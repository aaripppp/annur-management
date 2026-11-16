<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-headline-md font-headline-md text-on-surface">Kelompok Sopir Jemputan</h1>
            <p class="mt-1 text-body-md text-on-surface-variant">Kelola sopir dan riwayat pengelompokan siswa tanpa mengubah tagihan atau pembayaran.</p>
        </div>
        <button type="button" wire:click="openCreateDriver" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-label-lg font-label-lg text-on-primary shadow-sm transition-colors hover:bg-primary/90">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Sopir
        </button>
    </div>

    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
        <div class="border-b border-outline-variant p-4 sm:p-5">
            <label for="driver-search" class="sr-only">Cari sopir</label>
            <div class="relative max-w-md">
                <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                <input id="driver-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama sopir..." class="h-11 w-full rounded-lg border-outline-variant bg-surface pl-10 pr-3 text-body-md focus:border-primary focus:ring-primary">
            </div>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left">
                <thead class="bg-surface-container-low">
                    <tr class="border-b border-outline-variant">
                        <th class="w-16 px-4 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">No</th>
                        <th class="px-6 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Nama Sopir</th>
                        <th class="px-6 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Telepon</th>
                        <th class="px-6 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">Potongan Yayasan</th>
                        <th class="px-6 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">Siswa</th>
                        <th class="px-6 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">Status</th>
                        <th class="px-6 py-3 text-right text-label-sm uppercase tracking-wider text-on-surface-variant">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($drivers as $driver)
                        <tr wire:key="driver-row-{{ $driver->id }}" class="hover:bg-surface-container-low/50">
                            <td class="whitespace-nowrap px-4 py-4 text-center font-numeric-data text-on-surface-variant">{{ $drivers->firstItem() + $loop->index }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium text-on-surface">{{ $driver->name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-body-md text-on-surface-variant">{{ $driver->phone ?: '—' }}</td>
                            @php($currentRate = $currentRates[$driver->id] ?? null)
                            <td class="whitespace-nowrap px-6 py-4 text-center font-numeric-data text-on-surface">
                                <span>{{ $currentRate['label'] ?? 'Belum diatur' }}</span>
                                @if (($currentRate['source'] ?? null) === \App\Services\JemputanDriverDeductionRateResolver::SOURCE_LEGACY)
                                    <p class="mt-1 whitespace-normal text-body-sm font-normal text-on-surface-variant">Persentase lama — bulan berlaku belum ditentukan</p>
                                @elseif (! empty($currentRate['period_label']))
                                    <p class="mt-1 text-body-sm font-normal text-on-surface-variant">Mulai {{ $currentRate['period_label'] }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center font-numeric-data text-on-surface">{{ $driver->student_count }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex rounded-full px-3 py-1 text-label-sm font-label-sm {{ $driver->is_active ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                                    {{ $driver->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                    <button type="button" wire:click="viewGroup({{ $driver->id }})" title="Lihat Grup" aria-label="Lihat Grup {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">groups</span></button>
                                    <button type="button" wire:click="openDriverDeductionRateModal({{ $driver->id }})" title="Atur Persentase" aria-label="Atur Persentase {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">percent</span></button>
                                    <button type="button" wire:click="editDriver({{ $driver->id }})" title="Edit Sopir" aria-label="Edit Sopir {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span></button>
                                    <button type="button" wire:click="openDriverConfirmation({{ $driver->id }}, 'toggle')" title="{{ $driver->is_active ? 'Nonaktifkan Sopir' : 'Aktifkan Sopir' }}" aria-label="{{ $driver->is_active ? 'Nonaktifkan Sopir' : 'Aktifkan Sopir' }} {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $driver->is_active ? 'block' : 'check_circle' }}</span></button>
                                    <button type="button" wire:click="openDriverConfirmation({{ $driver->id }}, 'delete')" title="Hapus Sopir" aria-label="Hapus Sopir {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-error transition-colors hover:bg-error/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-body-md text-on-surface-variant">Belum ada data sopir yang sesuai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-outline-variant md:hidden">
            @forelse ($drivers as $driver)
                <article wire:key="driver-card-{{ $driver->id }}" class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-on-surface">{{ $driver->name }}</p>
                            <p class="truncate text-body-sm text-on-surface-variant">{{ $driver->phone ?: 'Tanpa nomor telepon' }} · {{ $driver->student_count }} siswa</p>
                            @php($currentRate = $currentRates[$driver->id] ?? null)
                            <p class="text-body-sm text-on-surface-variant">Potongan Yayasan: {{ $currentRate['label'] ?? 'Belum diatur' }}</p>
                            @if (($currentRate['source'] ?? null) === \App\Services\JemputanDriverDeductionRateResolver::SOURCE_LEGACY)
                                <p class="text-body-sm text-on-surface-variant">Persentase lama — bulan berlaku belum ditentukan</p>
                            @elseif (! empty($currentRate['period_label']))
                                <p class="text-body-sm text-on-surface-variant">Mulai {{ $currentRate['period_label'] }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-label-sm {{ $driver->is_active ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">{{ $driver->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="viewGroup({{ $driver->id }})" title="Lihat Grup" aria-label="Lihat Grup {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">groups</span></button>
                        <button type="button" wire:click="openDriverDeductionRateModal({{ $driver->id }})" title="Atur Persentase" aria-label="Atur Persentase {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">percent</span></button>
                        <button type="button" wire:click="editDriver({{ $driver->id }})" title="Edit Sopir" aria-label="Edit Sopir {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg bg-surface-container text-on-surface"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span></button>
                        <button type="button" wire:click="openDriverConfirmation({{ $driver->id }}, 'toggle')" title="{{ $driver->is_active ? 'Nonaktifkan Sopir' : 'Aktifkan Sopir' }}" aria-label="{{ $driver->is_active ? 'Nonaktifkan Sopir' : 'Aktifkan Sopir' }} {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg bg-surface-container text-on-surface"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $driver->is_active ? 'block' : 'check_circle' }}</span></button>
                        <button type="button" wire:click="openDriverConfirmation({{ $driver->id }}, 'delete')" title="Hapus Sopir" aria-label="Hapus Sopir {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg bg-error/10 text-error"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
                    </div>
                </article>
            @empty
                <p class="p-8 text-center text-body-md text-on-surface-variant">Belum ada data sopir yang sesuai.</p>
            @endforelse
        </div>

        @if ($drivers->hasPages())
            <div class="border-t border-outline-variant p-4">{{ $drivers->links() }}</div>
        @endif
    </section>

    @if ($isGroupModalOpen && $selectedDriver)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-hidden bg-inverse-surface/60 p-3 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-labelledby="driver-group-title" wire:click.self="closeGroup">
            <div class="flex h-full items-center justify-center">
                <section class="flex max-h-[calc(100vh-1.5rem)] w-full max-w-7xl flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-2xl sm:max-h-[calc(100vh-2rem)]">
                    <header class="flex shrink-0 flex-col gap-4 border-b border-outline-variant p-4 lg:flex-row lg:items-end lg:justify-between sm:p-5">
                        <div class="min-w-0">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 id="driver-group-title" class="text-title-lg font-title-lg text-on-surface">Kelompok {{ $selectedDriver->name }}</h2>
                                    <p class="mt-1 text-body-sm text-on-surface-variant">Kelola anggota aktif dan seluruh riwayat periode pada tahun ajaran yang dipilih.</p>
                                </div>
                                <button type="button" wire:click="closeGroup" title="Tutup" aria-label="Tutup modal kelompok sopir" class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container lg:hidden">
                                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            <div>
                                <label for="assignment-year" class="block text-label-md text-on-surface-variant">Tahun Ajaran</label>
                                <select id="assignment-year" wire:model.live="selectedAcademicYearId" class="mt-1 h-11 w-full rounded-lg border-outline-variant text-body-md focus:border-primary focus:ring-primary sm:w-auto">
                                    @foreach ($academicYears as $academicYear)
                                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" wire:click="openDeductionRateModal" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-primary px-4 py-2 text-label-lg text-primary transition-colors hover:bg-primary/10">
                                <span class="material-symbols-outlined text-[20px]">percent</span> Atur Persentase
                            </button>
                            <button type="button" wire:click="openAssignmentModal" @disabled(! $selectedDriver->is_active) class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-label-lg text-on-primary disabled:cursor-not-allowed disabled:opacity-50">
                                <span class="material-symbols-outlined text-[20px]">person_add</span> Tambah Siswa
                            </button>
                            <button type="button" wire:click="closeGroup" title="Tutup" aria-label="Tutup modal kelompok sopir" class="hidden size-11 shrink-0 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container lg:inline-flex">
                                <span class="material-symbols-outlined" aria-hidden="true">close</span>
                            </button>
                        </div>
                    </header>

                    @error('assignmentDriverId')<p class="m-4 shrink-0 rounded-lg bg-error-container px-4 py-3 text-body-sm text-on-error-container">{{ $message }}</p>@enderror

                    <div class="shrink-0 border-b border-outline-variant px-4 pt-3 sm:px-5">
                        <div class="flex gap-1" role="tablist" aria-label="Daftar anggota sopir">
                            <button type="button" role="tab" aria-selected="{{ $groupTab === 'active' ? 'true' : 'false' }}" wire:click="setGroupTab('active')" class="border-b-2 px-4 py-3 text-label-lg transition-colors {{ $groupTab === 'active' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">Anggota Aktif</button>
                            <button type="button" role="tab" aria-selected="{{ $groupTab === 'history' ? 'true' : 'false' }}" wire:click="setGroupTab('history')" class="border-b-2 px-4 py-3 text-label-lg transition-colors {{ $groupTab === 'history' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">Riwayat Anggota</button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                        @if ($groupTab === 'active')
                        <div class="overflow-x-auto" role="tabpanel">
                            <table class="w-full min-w-[1100px] border-collapse text-left">
                                <thead class="sticky top-0 z-10 bg-surface-container-low">
                                    <tr class="border-b border-outline-variant">
                                        <th class="w-16 px-5 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">No</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Siswa</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Kelas</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Tujuan</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Ket</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Bulan Mulai</th>
                                        <th class="px-5 py-3 text-right text-label-sm uppercase tracking-wider text-on-surface-variant">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @forelse ($assignments ?? [] as $assignment)
                                        @php($historicalClass = $assignment->student->enrollments->first()?->schoolClass)
                                        <tr wire:key="assignment-{{ $assignment->id }}" class="hover:bg-surface-container-low/50">
                                            <td class="whitespace-nowrap px-5 py-4 text-center font-numeric-data text-on-surface-variant">{{ $assignments->firstItem() + $loop->index }}</td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <p class="font-medium text-on-surface">{{ $assignment->student->nama_lengkap }}</p>
                                                <p class="text-body-sm text-on-surface-variant">{{ $assignment->student->nis }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $historicalClass?->name ?? '—' }}</td>
                                            <td class="px-5 py-4 text-body-md text-on-surface">{{ $assignment->destination ?: '—' }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $assignment->service_type ?: '—' }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $assignment->effective_from->locale('id')->translatedFormat('F Y') }}</td>
                                            <td class="px-5 py-4">
                                                <div class="flex justify-end gap-1 whitespace-nowrap">
                                                    <button type="button" wire:click="openEditAssignmentDetails({{ $assignment->id }})" title="Edit Detail Jemputan" aria-label="Edit Detail Jemputan {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_note</span></button>
                                                    <button type="button" wire:click="openEditAssignmentPeriod({{ $assignment->id }})" title="Edit Periode" aria-label="Edit Periode {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_calendar</span></button>
                                                    <button type="button" wire:click="openCloseAssignment({{ $assignment->id }})" title="Keluarkan Siswa" aria-label="Keluarkan Siswa {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-error transition-colors hover:bg-error/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">person_remove</span></button>
                                                    <button type="button" wire:click="openMoveAssignment({{ $assignment->id }})" title="Pindah Sopir" aria-label="Pindah Sopir untuk {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">swap_horiz</span></button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-6 py-10 text-center text-body-md text-on-surface-variant">Belum ada anggota aktif pada tahun ajaran ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($assignments?->hasPages())
                            <div class="border-t border-outline-variant p-4">{{ $assignments->links() }}</div>
                        @endif
                        @else
                        <div class="overflow-x-auto" role="tabpanel">
                            <table class="w-full min-w-[1250px] border-collapse text-left">
                                <thead class="sticky top-0 z-10 bg-surface-container-low">
                                    <tr class="border-b border-outline-variant">
                                        <th class="w-16 px-5 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">No</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Siswa</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Kelas</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Tujuan</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Ket</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Mulai</th>
                                        <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Berhenti</th>
                                        <th class="px-5 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">Status</th>
                                        <th class="px-5 py-3 text-right text-label-sm uppercase tracking-wider text-on-surface-variant">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @forelse ($assignmentHistory ?? [] as $assignment)
                                        @php($historicalClass = $assignment->student->enrollments->first()?->schoolClass)
                                        @php($invalidPeriod = $assignment->effective_until !== null && ! $assignment->effective_until->gt($assignment->effective_from))
                                        <tr wire:key="assignment-history-{{ $assignment->id }}" class="hover:bg-surface-container-low/50">
                                            <td class="whitespace-nowrap px-5 py-4 text-center font-numeric-data text-on-surface-variant">{{ $assignmentHistory->firstItem() + $loop->index }}</td>
                                            <td class="whitespace-nowrap px-5 py-4">
                                                <p class="font-medium text-on-surface">{{ $assignment->student->nama_lengkap }}</p>
                                                <p class="text-body-sm text-on-surface-variant">{{ $assignment->student->nis }}</p>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $historicalClass?->name ?? '—' }}</td>
                                            <td class="px-5 py-4 text-body-md text-on-surface">{{ $assignment->destination ?: '—' }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $assignment->service_type ?: '—' }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $assignment->effective_from->locale('id')->translatedFormat('F Y') }}</td>
                                            <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $assignment->effective_until?->locale('id')->translatedFormat('F Y') ?? '—' }}</td>
                                            <td class="px-5 py-4 text-center">
                                                <span class="inline-flex rounded-full px-3 py-1 text-label-sm {{ $invalidPeriod ? 'bg-error-container text-on-error-container' : ($assignment->effective_until === null ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant') }}">
                                                    {{ $invalidPeriod ? 'Periode Tidak Valid' : ($assignment->effective_until === null ? 'Aktif' : 'Selesai') }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <button type="button" wire:click="openEditAssignmentDetails({{ $assignment->id }})" title="Edit Detail Jemputan" aria-label="Edit Detail Jemputan {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_note</span></button>
                                                <button type="button" wire:click="openEditAssignmentPeriod({{ $assignment->id }})" title="Edit Periode" aria-label="Edit Periode {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit_calendar</span></button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="px-6 py-10 text-center text-body-md text-on-surface-variant">Belum ada riwayat anggota pada tahun ajaran ini.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($assignmentHistory?->hasPages())
                            <div class="border-t border-outline-variant p-4">{{ $assignmentHistory->links() }}</div>
                        @endif
                        @endif
                    </div>
                </section>
            </div>
        </div>
        </template>
    @endif

    @if ($isDriverModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="closeDriverModal">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-lg rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                <h2 class="text-title-lg font-title-lg text-on-surface">{{ $editingDriverId ? 'Edit Sopir' : 'Tambah Sopir' }}</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <label for="driver-name" class="block text-label-md text-on-surface">Nama Sopir</label>
                        <input id="driver-name" type="text" wire:model="driverName" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('driverName')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="driver-phone" class="block text-label-md text-on-surface">Nomor Telepon <span class="text-on-surface-variant">(opsional)</span></label>
                        <input id="driver-phone" type="text" wire:model="driverPhone" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('driverPhone')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    @if ($editingDriverId === null)
                        <div>
                            <label for="driver-foundation-deduction-percentage" class="block text-label-md text-on-surface">Potongan Yayasan (%) <span class="text-on-surface-variant">(opsional)</span></label>
                            <input id="driver-foundation-deduction-percentage" type="number" min="0" max="100" step="0.01" inputmode="decimal" wire:model="driverFoundationDeductionPercentage" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                            @error('driverFoundationDeductionPercentage')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                            <p class="mt-1 text-body-sm text-on-surface-variant">Persentase awal disimpan sebagai riwayat tarif, bukan sebagai kolom persentase lama.</p>
                        </div>
                        <div>
                            <label for="driver-deduction-effective-month" class="block text-label-md text-on-surface">Bulan Mulai Berlaku</label>
                            <input id="driver-deduction-effective-month" type="month" wire:model="driverDeductionEffectiveMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                            @error('driverDeductionEffectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <div class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3">
                            <p class="text-body-sm text-on-surface-variant">Persentase yayasan tidak diubah dari form ini. Gunakan tombol <span class="font-label-md text-on-surface">Atur Persentase</span> pada daftar sopir agar riwayat tarif tetap utuh.</p>
                        </div>
                    @endif
                    <label class="flex items-center gap-3 text-body-md text-on-surface">
                        <input type="checkbox" wire:model="driverIsActive" class="rounded border-outline-variant text-primary focus:ring-primary"> Sopir aktif
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeDriverModal" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="saveDriver" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary">Simpan</button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isAssignmentModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="closeAssignmentModal">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-xl rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                <h2 class="text-title-lg font-title-lg text-on-surface">Tambah Siswa ke {{ $selectedDriver?->name }}</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        @if ($selectedStudent)
                            @php($selectedStudentClass = $selectedStudent->enrollments->first()?->schoolClass)
                            <p class="block text-label-md text-on-surface">Siswa Terpilih</p>
                            <div class="mt-1 flex items-center justify-between gap-4 rounded-xl border border-primary/30 bg-primary-container/40 p-4">
                                <div class="min-w-0">
                                    <p class="truncate text-body-lg font-label-lg text-on-surface">{{ $selectedStudent->nama_lengkap }}</p>
                                    <p class="mt-1 text-body-sm text-on-surface-variant">{{ $selectedStudent->nis ?: 'NIS —' }} · {{ $selectedStudentClass?->name ?? 'Kelas —' }}</p>
                                </div>
                                <button type="button" wire:click="changeStudent" class="shrink-0 rounded-lg px-3 py-2 text-label-md text-primary transition-colors hover:bg-primary/10" aria-label="Ganti Siswa">Ganti Siswa</button>
                            </div>
                        @elseif ($selectedStudentId !== null)
                            <p class="block text-label-md text-on-surface">Siswa Terpilih</p>
                            <div class="mt-1 flex items-center justify-between gap-4 rounded-xl border border-error/30 bg-error-container/40 p-4">
                                <p class="text-body-sm text-on-error-container">Pilihan siswa tidak lagi valid. Silakan pilih kembali.</p>
                                <button type="button" wire:click="changeStudent" class="shrink-0 rounded-lg px-3 py-2 text-label-md text-primary transition-colors hover:bg-primary/10" aria-label="Ganti Siswa">Ganti Siswa</button>
                            </div>
                        @else
                            <label for="student-search" class="block text-label-md text-on-surface">Cari Siswa</label>
                            <input id="student-search" type="search" wire:model.live.debounce.300ms="studentSearch" placeholder="Ketik nama atau NIS..." autocomplete="off" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @endif
                        @if ($selectedStudentId === null && $studentResults->isNotEmpty())
                            <div class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-outline-variant bg-surface shadow-sm">
                                @foreach ($studentResults as $student)
                                    @php($studentClass = $student->enrollments->first()?->schoolClass)
                                    <button type="button" wire:click="selectStudent({{ $student->id }})" class="flex w-full items-center justify-between gap-3 border-b border-outline-variant px-3 py-2.5 text-left last:border-0 hover:bg-surface-container-low">
                                        <span class="truncate text-body-md text-on-surface">{{ $student->nama_lengkap }}</span>
                                        <span class="shrink-0 text-body-sm text-on-surface-variant">{{ $student->nis }} · {{ $studentClass?->name ?? '—' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('selectedStudentId')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="assignment-destination" class="block text-label-md text-on-surface">Tujuan <span class="text-on-surface-variant">(opsional)</span></label>
                        <input id="assignment-destination" type="text" maxlength="255" wire:model="assignmentDestination" placeholder="Contoh: Cikarang Baru" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('assignmentDestination')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="assignment-service-type" class="block text-label-md text-on-surface">Keterangan <span class="text-on-surface-variant">(opsional)</span></label>
                        <select id="assignment-service-type" wire:model="assignmentServiceType" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                            <option value="">Pilih keterangan</option>
                            @foreach (\App\Models\JemputanDriverAssignment::SERVICE_TYPES as $serviceType)
                                <option value="{{ $serviceType }}">{{ $serviceType }}</option>
                            @endforeach
                        </select>
                        @error('assignmentServiceType')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="assignment-effective-month" class="block text-label-md text-on-surface">Bulan Mulai Efektif</label>
                        <input id="assignment-effective-month" type="month" wire:model="assignmentEffectiveMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('assignmentEffectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('effectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        <p class="mt-1 text-body-sm text-on-surface-variant">Keanggotaan berlaku mulai awal bulan yang dipilih. Tagihan tidak dibuat otomatis.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeAssignmentModal" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="assignStudent" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary">Tambahkan</button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isEditAssignmentDetailsModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="edit-assignment-details-title" wire:click.self="cancelEditAssignmentDetails">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-lg rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                <h2 id="edit-assignment-details-title" class="text-title-lg font-title-lg text-on-surface">Edit Detail Jemputan</h2>
                <p class="mt-1 text-body-sm text-on-surface-variant">Perubahan ini tidak mengubah periode membership maupun data finansial.</p>

                <dl class="mt-5 grid grid-cols-1 gap-3 rounded-lg border border-outline-variant bg-surface-container-low p-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-body-sm text-on-surface-variant">Siswa</dt>
                        <dd class="mt-0.5 font-medium text-on-surface">{{ $editAssignmentDetailsStudentName }}</dd>
                    </div>
                    <div>
                        <dt class="text-body-sm text-on-surface-variant">Sopir</dt>
                        <dd class="mt-0.5 font-medium text-on-surface">{{ $editAssignmentDetailsDriverName }}</dd>
                    </div>
                </dl>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="edit-assignment-destination" class="block text-label-md text-on-surface">Tujuan <span class="text-on-surface-variant">(opsional)</span></label>
                        <input id="edit-assignment-destination" type="text" maxlength="255" wire:model="editAssignmentDestination" placeholder="Contoh: Cikarang Baru" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('editAssignmentDestination')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('destination')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="edit-assignment-service-type" class="block text-label-md text-on-surface">Keterangan <span class="text-on-surface-variant">(opsional)</span></label>
                        <select id="edit-assignment-service-type" wire:model="editAssignmentServiceType" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                            <option value="">Pilih keterangan</option>
                            @foreach (\App\Models\JemputanDriverAssignment::SERVICE_TYPES as $serviceType)
                                <option value="{{ $serviceType }}">{{ $serviceType }}</option>
                            @endforeach
                        </select>
                        @error('editAssignmentServiceType')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('service_type')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelEditAssignmentDetails" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="saveAssignmentDetails" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary">Simpan</button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isCloseModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="cancelCloseAssignment">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-md rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <h2 class="text-title-lg font-title-lg text-on-surface">Keluarkan Siswa</h2>
                <p class="mt-2 text-body-sm text-on-surface-variant">Riwayat sebelum bulan berhenti tetap tersimpan dan siswa dapat ditambahkan kembali pada periode berikutnya.</p>
                <div class="mt-5">
                    <label for="close-effective-month" class="block text-label-md text-on-surface">Bulan Efektif Berhenti</label>
                    <input id="close-effective-month" type="month" wire:model="closeEffectiveMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                    @error('closeEffectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    @error('effectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    @error('effective_until')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    @error('assignmentPeriod')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelCloseAssignment" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="closeAssignment" class="rounded-lg bg-error px-4 py-2.5 text-label-lg text-on-error">Keluarkan Siswa</button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isMoveModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="cancelMoveAssignment">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-md rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <h2 class="text-title-lg font-title-lg text-on-surface">Pindah Sopir</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <label for="move-driver" class="block text-label-md text-on-surface">Sopir Tujuan</label>
                        <select id="move-driver" wire:model="moveDriverId" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                            <option value="">Pilih sopir</option>
                            @foreach ($activeDrivers as $driver)
                                @if ($driver->id !== $selectedDriverId)
                                    <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('moveDriverId')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="move-effective-month" class="block text-label-md text-on-surface">Bulan Efektif Pindah</label>
                        <input id="move-effective-month" type="month" wire:model="moveEffectiveMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('moveEffectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('effectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('effective_until')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('assignmentPeriod')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelMoveAssignment" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="moveAssignment" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary">Pindahkan</button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isEditAssignmentPeriodModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="edit-assignment-period-title" wire:click.self="cancelEditAssignmentPeriod">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-lg rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                <h2 id="edit-assignment-period-title" class="text-title-lg font-title-lg text-on-surface">Edit Periode Keanggotaan</h2>
                <p class="mt-1 text-body-sm text-on-surface-variant">Hanya periode assignment yang dipilih yang akan diperbarui.</p>

                <dl class="mt-5 grid grid-cols-1 gap-3 rounded-lg border border-outline-variant bg-surface-container-low p-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-body-sm text-on-surface-variant">Siswa</dt>
                        <dd class="mt-0.5 font-medium text-on-surface">{{ $editAssignmentStudentName }}</dd>
                    </div>
                    <div>
                        <dt class="text-body-sm text-on-surface-variant">Sopir</dt>
                        <dd class="mt-0.5 font-medium text-on-surface">{{ $editAssignmentDriverName }}</dd>
                    </div>
                </dl>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="edit-assignment-from" class="block text-label-md text-on-surface">Bulan Mulai Efektif</label>
                        <input id="edit-assignment-from" type="month" wire:model.live="editAssignmentEffectiveFromMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('editAssignmentEffectiveFromMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('effective_from')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('effectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="edit-assignment-until" class="block text-label-md text-on-surface">Bulan Berhenti Efektif <span class="text-on-surface-variant">(opsional)</span></label>
                        <input id="edit-assignment-until" type="month" wire:model.live="editAssignmentEffectiveUntilMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('editAssignmentEffectiveUntilMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                        @error('effective_until')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                    @error('assignmentPeriod')<p class="text-body-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5 rounded-lg border border-tertiary/30 bg-tertiary-fixed/20 px-4 py-3 text-body-sm text-on-surface">
                    <p class="font-label-md">Periksa dampak historis</p>
                    <p class="mt-1 text-on-surface-variant">Koreksi periode dapat mengubah laporan historis. Tagihan, pembayaran, adjustment, dan alokasi tidak akan diubah otomatis.</p>
                </div>

                @if ($editPeriodRequiresConfirmation)
                    <div class="mt-4 rounded-lg border border-error/30 bg-error-container/30 px-4 py-3 text-body-sm text-on-error-container">
                        Bulan yang berubah memiliki tagihan atau alokasi Multi-Sopir. Simpan hanya setelah dampaknya diperiksa.
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelEditAssignmentPeriod" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                        <button type="button" wire:click="confirmAssignmentPeriodImpact" class="rounded-lg bg-tertiary-fixed px-4 py-2.5 text-label-lg text-on-tertiary-fixed">Ya, Simpan Koreksi Historis</button>
                    </div>
                @else
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="cancelEditAssignmentPeriod" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                        <button type="button" wire:click="saveAssignmentPeriod" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary">Simpan Periode</button>
                    </div>
                @endif
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isDriverConfirmationModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="cancelDriverConfirmation">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-md rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <h2 class="text-title-lg font-title-lg text-on-surface">{{ $driverConfirmationAction === 'delete' ? 'Hapus Sopir' : 'Ubah Status Sopir' }}</h2>
                <p class="mt-2 text-body-md text-on-surface-variant">
                    {{ $driverConfirmationAction === 'delete'
                        ? 'Sopir hanya dapat dihapus jika belum memiliki riwayat penugasan.'
                        : 'Perubahan status tidak menghapus riwayat anggota maupun data keuangan.' }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="cancelDriverConfirmation" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="confirmDriverAction" class="rounded-lg {{ $driverConfirmationAction === 'delete' ? 'bg-error text-on-error' : 'bg-primary text-on-primary' }} px-4 py-2.5 text-label-lg">Konfirmasi</button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isDeductionRateModalOpen && $selectedDriver)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="closeDeductionRateModal">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative max-h-[calc(100vh-2rem)] w-full max-w-5xl overflow-y-auto overscroll-contain rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                <h2 class="text-title-lg font-title-lg text-on-surface">Ubah Persentase Yayasan</h2>
                <p class="mt-1 text-body-sm text-on-surface-variant">{{ $selectedDriver->name }}</p>

                <div class="mt-5 space-y-4">
                    <div class="rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3">
                        <p class="text-body-sm text-on-surface-variant">Persentase saat ini</p>
                        <p class="mt-0.5 text-title-md font-title-md text-on-surface">{{ $selectedDriverCurrentRate['label'] ?? 'Belum diatur' }}</p>
                        @if (($selectedDriverCurrentRate['source'] ?? null) === \App\Services\JemputanDriverDeductionRateResolver::SOURCE_LEGACY)
                            <p class="mt-1 text-body-sm text-on-surface-variant">Persentase lama — bulan berlaku belum ditentukan</p>
                        @elseif (! empty($selectedDriverCurrentRate['period_label']))
                            <p class="mt-1 text-body-sm text-on-surface-variant">Mulai berlaku {{ $selectedDriverCurrentRate['period_label'] }}</p>
                        @endif
                    </div>

                    <div>
                        <label for="deduction-rate-percentage" class="block text-label-md text-on-surface">Persentase Baru (%)</label>
                        <input id="deduction-rate-percentage" type="number" min="0" max="100" step="0.01" inputmode="decimal" wire:model.live.debounce.300ms="deductionRatePercentage" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('deductionRatePercentage')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="deduction-rate-effective-month" class="block text-label-md text-on-surface">Bulan Mulai Berlaku</label>
                        <input id="deduction-rate-effective-month" type="month" wire:model.live="deductionRateEffectiveMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('deductionRateEffectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="deduction-rate-notes" class="block text-label-md text-on-surface">Catatan <span class="text-on-surface-variant">(opsional)</span></label>
                        <textarea id="deduction-rate-notes" rows="2" wire:model="deductionRateNotes" placeholder="Contoh: Penyesuaian tarif yayasan" class="mt-1 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary"></textarea>
                        @error('deductionRateNotes')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    @if ($deductionRatePreview)
                        <div class="rounded-lg border border-primary/30 bg-primary-container/30 px-4 py-3">
                            <p class="text-label-md font-label-md text-on-surface">Preview dampak perubahan</p>
                            @if ($deductionRatePreview['previous_source'] === \App\Services\JemputanDriverDeductionRateResolver::SOURCE_LEGACY)
                                <p class="mt-1 text-body-sm text-on-surface-variant">
                                    Persentase lama {{ $deductionRatePreview['previous_label_percentage'] }} masih digunakan sementara sampai {{ $deductionRatePreview['effective_label'] }}.
                                </p>
                            @elseif ($deductionRatePreview['previous_configured'])
                                <p class="mt-1 text-body-sm text-on-surface-variant">
                                    {{ $deductionRatePreview['previous_label'] }} tetap {{ $deductionRatePreview['previous_label_percentage'] }}.
                                </p>
                            @else
                                <p class="mt-1 text-body-sm text-on-surface-variant">
                                    {{ $deductionRatePreview['previous_label'] }} belum diatur.
                                </p>
                            @endif
                            <p class="text-body-sm text-on-surface-variant">
                                {{ $deductionRatePreview['effective_label'] }} menjadi {{ $deductionRatePreview['new_label'] }}.
                            </p>
                        </div>

                        @if ($deductionRatePreview['duplicate'])
                            <div class="rounded-lg border border-error/30 bg-error-container/20 px-4 py-3 text-body-sm text-on-error-container">
                                Tarif untuk {{ $deductionRatePreview['effective_label'] }} sudah ada. Pilih periode lain; data historis tidak akan ditimpa.
                            </div>
                        @endif

                        @if ($deductionRatePreview['is_retroactive'])
                            <div class="rounded-lg border border-tertiary/30 bg-tertiary-fixed/20 px-4 py-3 text-body-sm text-on-surface">
                                <p class="font-label-md">Perubahan berlaku mundur.</p>
                                <p class="mt-1 text-on-surface-variant">Menambahkan tarif untuk periode yang sudah lewat dapat mengubah laporan historis. Periksa dampaknya sebelum menyimpan.</p>
                            </div>
                        @endif
                    @endif
                </div>

                <section class="mt-6 overflow-hidden rounded-xl border border-outline-variant">
                    <div class="bg-surface-container-low px-4 py-3 sm:px-5">
                        <h3 class="text-title-md font-title-md text-on-surface">Riwayat Persentase Yayasan</h3>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Setiap tarif berlaku sampai ada tarif berikutnya. Tarif lama tidak berubah saat tarif baru ditambahkan.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[620px] border-collapse text-left">
                            <thead class="bg-surface-container-lowest">
                                <tr class="border-y border-outline-variant">
                                    <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Mulai Berlaku</th>
                                    <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Persentase</th>
                                    <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Keterangan</th>
                                    <th class="px-5 py-3 text-right text-label-sm uppercase tracking-wider text-on-surface-variant">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @forelse ($selectedDriverRateHistory as $deductionRate)
                                    <tr wire:key="deduction-rate-{{ $deductionRate['id'] }}">
                                        <td class="whitespace-nowrap px-5 py-3 text-body-md text-on-surface">{{ $deductionRate['period_label'] }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 font-numeric-data text-body-md text-on-surface">{{ $deductionRate['percentage_label'] }}</td>
                                        <td class="px-5 py-3 text-body-sm text-on-surface-variant">{{ $deductionRate['notes'] ?: '—' }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <button type="button" wire:click="openEditDeductionRateModal({{ $deductionRate['id'] }})" title="Edit riwayat persentase" aria-label="Edit riwayat persentase {{ $deductionRate['period_label'] }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10">
                                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-body-md text-on-surface-variant">
                                            Belum ada riwayat tarif.
                                            @if (($selectedDriverCurrentRate['source'] ?? null) === \App\Services\JemputanDriverDeductionRateResolver::SOURCE_LEGACY)
                                                Persentase lama masih digunakan sementara sampai tarif pertama ditambahkan.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if ($deductionRateRequiresConfirmation)
                    <div class="mt-5 rounded-lg border border-tertiary/30 bg-tertiary-fixed/20 px-4 py-3 text-body-sm text-on-surface">
                        <p class="font-label-md">Konfirmasi tarif historis</p>
                        <p class="mt-1 text-on-surface-variant">Tarif ini akan berlaku mundur. Simpan hanya jika dampak historis sudah diperiksa.</p>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="closeDeductionRateModal" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                        <button type="button" wire:click="confirmRetroactiveDeductionRate" class="rounded-lg bg-tertiary-fixed px-4 py-2.5 text-label-lg text-on-tertiary-fixed">Ya, Simpan Tarif Historis</button>
                    </div>
                @else
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" wire:click="closeDeductionRateModal" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                        <button type="button" wire:click="saveDeductionRate" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary">Simpan</button>
                    </div>
                @endif
            </div>
            </div>
        </div>
        </template>
    @endif

    @if ($isEditDeductionRateModalOpen && $selectedDriver)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="edit-deduction-rate-title" wire:click.self="closeEditDeductionRateModal">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-lg rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl sm:p-6">
                <h2 id="edit-deduction-rate-title" class="text-title-lg font-title-lg text-on-surface">Edit Riwayat Persentase</h2>
                <p class="mt-1 text-body-sm text-on-surface-variant">{{ $selectedDriver->name }}</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="edit-deduction-rate-percentage" class="block text-label-md text-on-surface">Persentase (%)</label>
                        <input id="edit-deduction-rate-percentage" type="number" min="0" max="100" step="0.01" inputmode="decimal" wire:model="editDeductionRatePercentage" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('editDeductionRatePercentage')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="edit-deduction-rate-effective-month" class="block text-label-md text-on-surface">Bulan Mulai Berlaku</label>
                        <input id="edit-deduction-rate-effective-month" type="month" wire:model="editDeductionRateEffectiveMonth" class="mt-1 h-11 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary">
                        @error('editDeductionRateEffectiveMonth')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="edit-deduction-rate-notes" class="block text-label-md text-on-surface">Catatan <span class="text-on-surface-variant">(opsional)</span></label>
                        <textarea id="edit-deduction-rate-notes" rows="3" maxlength="500" wire:model="editDeductionRateNotes" class="mt-1 w-full rounded-lg border-outline-variant focus:border-primary focus:ring-primary"></textarea>
                        @error('editDeductionRateNotes')<p class="mt-1 text-body-sm text-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                @error('editDeductionRate')<p class="mt-2 text-body-sm text-error">{{ $message }}</p>@enderror

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeEditDeductionRateModal" class="rounded-lg px-4 py-2.5 text-label-lg text-on-surface hover:bg-surface-container">Batal</button>
                    <button type="button" wire:click="saveDeductionRateCorrection" wire:loading.attr="disabled" wire:target="saveDeductionRateCorrection" class="rounded-lg bg-primary px-4 py-2.5 text-label-lg text-on-primary disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveDeductionRateCorrection">Simpan</span>
                        <span wire:loading wire:target="saveDeductionRateCorrection">Menyimpan...</span>
                    </button>
                </div>
            </div>
            </div>
        </div>
        </template>
    @endif
</div>
