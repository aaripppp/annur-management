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
                        <th class="px-6 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Nama Sopir</th>
                        <th class="px-6 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Telepon</th>
                        <th class="px-6 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">Siswa</th>
                        <th class="px-6 py-3 text-center text-label-sm uppercase tracking-wider text-on-surface-variant">Status</th>
                        <th class="px-6 py-3 text-right text-label-sm uppercase tracking-wider text-on-surface-variant">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($drivers as $driver)
                        <tr wire:key="driver-row-{{ $driver->id }}" class="hover:bg-surface-container-low/50">
                            <td class="whitespace-nowrap px-6 py-4 font-medium text-on-surface">{{ $driver->name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-body-md text-on-surface-variant">{{ $driver->phone ?: '—' }}</td>
                            <td class="px-6 py-4 text-center font-numeric-data text-on-surface">{{ $driver->student_count }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex rounded-full px-3 py-1 text-label-sm font-label-sm {{ $driver->is_active ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                                    {{ $driver->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                    <button type="button" wire:click="viewGroup({{ $driver->id }})" title="Lihat Grup" aria-label="Lihat Grup {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">groups</span></button>
                                    <button type="button" wire:click="editDriver({{ $driver->id }})" title="Edit Sopir" aria-label="Edit Sopir {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span></button>
                                    <button type="button" wire:click="openDriverConfirmation({{ $driver->id }}, 'toggle')" title="{{ $driver->is_active ? 'Nonaktifkan Sopir' : 'Aktifkan Sopir' }}" aria-label="{{ $driver->is_active ? 'Nonaktifkan Sopir' : 'Aktifkan Sopir' }} {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-on-surface transition-colors hover:bg-surface-container"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $driver->is_active ? 'block' : 'check_circle' }}</span></button>
                                    <button type="button" wire:click="openDriverConfirmation({{ $driver->id }}, 'delete')" title="Hapus Sopir" aria-label="Hapus Sopir {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg text-error transition-colors hover:bg-error/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-body-md text-on-surface-variant">Belum ada data sopir yang sesuai.</td></tr>
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
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-label-sm {{ $driver->is_active ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">{{ $driver->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="viewGroup({{ $driver->id }})" title="Lihat Grup" aria-label="Lihat Grup {{ $driver->name }}" class="inline-flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">groups</span></button>
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

    @if ($selectedDriver)
        <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-sm">
            <header class="flex flex-col gap-4 border-b border-outline-variant p-4 sm:flex-row sm:items-end sm:justify-between sm:p-5">
                <div>
                    <button type="button" wire:click="closeGroup" class="mb-2 inline-flex items-center gap-1 text-label-md text-primary hover:underline">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali ke daftar
                    </button>
                    <h2 class="text-title-lg font-title-lg text-on-surface">Kelompok {{ $selectedDriver->name }}</h2>
                    <p class="mt-1 text-body-sm text-on-surface-variant">Anggota aktif pada tahun ajaran yang dipilih. Riwayat penugasan tetap tersimpan.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div>
                        <label for="assignment-year" class="block text-label-md text-on-surface-variant">Tahun Ajaran</label>
                        <select id="assignment-year" wire:model.live="selectedAcademicYearId" class="mt-1 h-11 rounded-lg border-outline-variant text-body-md focus:border-primary focus:ring-primary">
                            @foreach ($academicYears as $academicYear)
                                <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" wire:click="openAssignmentModal" @disabled(! $selectedDriver->is_active) class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-label-lg text-on-primary disabled:cursor-not-allowed disabled:opacity-50">
                        <span class="material-symbols-outlined text-[20px]">person_add</span> Tambah Siswa
                    </button>
                </div>
            </header>

            @error('assignmentDriverId')<p class="m-4 rounded-lg bg-error-container px-4 py-3 text-body-sm text-on-error-container">{{ $message }}</p>@enderror

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-left">
                    <thead class="bg-surface-container-low">
                        <tr class="border-b border-outline-variant">
                            <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Siswa</th>
                            <th class="px-5 py-3 text-label-sm uppercase tracking-wider text-on-surface-variant">Kelas</th>
                            <th class="px-5 py-3 text-right text-label-sm uppercase tracking-wider text-on-surface-variant">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @forelse ($assignments ?? [] as $assignment)
                            @php($historicalClass = $assignment->student->enrollments->first()?->schoolClass)
                            <tr wire:key="assignment-{{ $assignment->id }}" class="hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="font-medium text-on-surface">{{ $assignment->student->nama_lengkap }}</p>
                                    <p class="text-body-sm text-on-surface-variant">{{ $assignment->student->nis }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-body-md text-on-surface">{{ $historicalClass?->name ?? '—' }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2 whitespace-nowrap">
                                        <button type="button" wire:click="openCloseAssignment({{ $assignment->id }})" title="Keluarkan Siswa" aria-label="Keluarkan Siswa {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-error transition-colors hover:bg-error/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">person_remove</span></button>
                                        <button type="button" wire:click="openMoveAssignment({{ $assignment->id }})" title="Pindah Sopir" aria-label="Pindah Sopir untuk {{ $assignment->student->nama_lengkap }}" class="inline-flex size-10 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10"><span class="material-symbols-outlined text-[20px]" aria-hidden="true">swap_horiz</span></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-10 text-center text-body-md text-on-surface-variant">Belum ada anggota siswa pada tahun ajaran ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($assignments?->hasPages())
                <div class="border-t border-outline-variant p-4">{{ $assignments->links() }}</div>
            @endif
        </section>
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

    @if ($isCloseModalOpen)
        <template x-teleport="body">
        <div class="fixed inset-0 z-[9999] overflow-y-auto bg-inverse-surface/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" wire:click.self="cancelCloseAssignment">
            <div class="flex min-h-full items-center justify-center">
            <div class="relative w-full max-w-md rounded-xl border border-outline-variant bg-surface-container-lowest p-5 shadow-2xl">
                <h2 class="text-title-lg font-title-lg text-on-surface">Keluarkan Siswa</h2>
                <p class="mt-2 text-body-sm text-on-surface-variant">Siswa langsung tidak lagi menjadi anggota aktif dan dapat ditambahkan kembali kapan saja. Riwayat lama tetap tersimpan.</p>
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
</div>
