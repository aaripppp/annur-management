<div>
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-4">
        <a href="{{ route('pembayaran.index') }}" wire:navigate class="hover:text-primary">Pembayaran</a>
        <span>&rsaquo;</span>
        <a href="{{ route('calon-siswa.index') }}" wire:navigate class="hover:text-primary">Calon Siswa</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">{{ $prospectiveStudent->nama_lengkap }}</span>
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

    @if(session()->has('error'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            x-transition
            class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]"
        >
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl px-5 py-5 sm:px-6 shadow-sm mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 lg:gap-6">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-full bg-surface-container-high border border-outline-variant text-on-surface-variant flex items-center justify-center font-bold text-title-lg shrink-0" aria-hidden="true">{{ strtoupper(substr($prospectiveStudent->nama_lengkap, 0, 2)) }}</div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-headline-md font-headline-md text-on-surface leading-tight">
                            <span class="sr-only">Profil Calon Siswa: </span>{{ $prospectiveStudent->nama_lengkap }}
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $prospectiveStudent->status->value === 'registered' ? 'bg-primary-fixed text-on-primary-fixed' : ($prospectiveStudent->status->value === 'converted' ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-error-container text-on-error-container') }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $prospectiveStudent->status->value === 'registered' ? 'bg-primary' : ($prospectiveStudent->status->value === 'converted' ? 'bg-secondary' : 'bg-error') }}"></span>
                            {{ $prospectiveStudent->status_label }}
                        </span>
                    </div>
                    <p class="text-body-sm text-on-surface-variant mt-1.5 font-numeric-data">{{ $prospectiveStudent->registration_number }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0 lg:justify-end">
                <a
                    href="{{ route('pembayaran.index') }}"
                    wire:navigate
                    class="px-3.5 py-2 text-on-surface-variant font-label-md border border-outline-variant rounded-xl hover:bg-surface-container transition-colors flex items-center justify-center gap-1.5"
                >
                    <span class="material-symbols-outlined text-[18px]">person_search</span>
                    Ganti Siswa
                </a>
@if($prospectiveStudent->status === \App\Enums\ProspectiveStudentStatus::Registered)
                    <button
                        type="button"
                        wire:click="openConvertModal"
                        class="px-4 py-2 bg-primary hover:bg-primary/90 text-on-primary font-label-md rounded-xl transition-colors shadow-sm flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[20px]">person_add</span>
                        Jadikan Siswa
                    </button>
                @endif
                @if(! $prospectiveStudent->isConverted())
                    <button
                        type="button"
                        wire:click="openEditProfile"
                        class="px-3.5 py-2 text-primary font-label-md border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center justify-center gap-1.5"
                    >
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                        Edit Profil
                    </button>
                @endif
                <a
                    href="{{ route('pembayaran.prospective.create', $prospectiveStudent) }}"
                    wire:navigate
                    class="px-4 py-2 bg-primary hover:bg-primary/90 text-on-primary font-label-md rounded-xl transition-colors shadow-sm flex items-center justify-center gap-1.5"
                >
                    <span class="material-symbols-outlined text-[20px]">add</span>
                    Input Pembayaran
                </a>
            </div>
        </div>

        <div class="mt-5 pt-5 border-t border-outline-variant">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4">
                <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="academic-year">
                    <div class="w-9 h-9 rounded-lg bg-primary-fixed/60 text-primary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">calendar_today</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tahun Ajaran Tujuan</p><p class="text-body-md font-semibold text-on-surface mt-0.5 font-numeric-data">{{ $prospectiveStudent->academicYear?->year ?? '-' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:px-6" data-profile-field="class">
                    <div class="w-9 h-9 rounded-lg bg-secondary-container/60 text-secondary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">school</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Kelas Tujuan</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $prospectiveStudent->schoolClass?->name ?? 'Belum Ditentukan' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:pl-6" data-profile-field="level">
                    <div class="w-9 h-9 rounded-lg bg-tertiary-fixed/60 text-on-tertiary-fixed flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">account_tree</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Jenjang Tujuan</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $prospectiveStudent->target_level_label ?? 'Belum Ditentukan' }}</p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4 mt-4 pt-4 border-t border-outline-variant/60">
                <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="gender">
                    <div class="w-9 h-9 rounded-lg bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">person</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Jenis Kelamin</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $prospectiveStudent->jenis_kelamin === 'L' ? 'Laki-laki' : ($prospectiveStudent->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:px-6" data-profile-field="parent">
                    <div class="w-9 h-9 rounded-lg bg-tertiary-fixed/60 text-on-tertiary-fixed flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">person_2</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Orang Tua</p><p class="text-body-md font-semibold text-on-surface mt-0.5 break-words">{{ $prospectiveStudent->nama_orang_tua ?? '-' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:pl-6" data-profile-field="phone">
                    <div class="w-9 h-9 rounded-lg bg-error-container/60 text-error flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">phone</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">No. HP</p><p class="text-body-md font-semibold text-on-surface mt-0.5 font-numeric-data">{{ $prospectiveStudent->no_telp_orang_tua ?? '-' }}</p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mt-4 pt-4 border-t border-outline-variant/60">
                <div class="flex items-start gap-3 min-w-0" data-profile-field="address">
                    <div class="w-9 h-9 rounded-lg bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">location_on</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Alamat</p><p class="text-body-md font-semibold text-on-surface mt-0.5 whitespace-pre-line break-words">{{ $prospectiveStudent->alamat ?? '-' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0" data-profile-field="notes">
                    <div class="w-9 h-9 rounded-lg bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">sticky_note_2</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Catatan</p><p class="text-body-md font-semibold text-on-surface mt-0.5 whitespace-pre-line break-words">{{ $prospectiveStudent->notes ?? '-' }}</p></div>
                </div>
            </div>

            @if($prospectiveStudent->isConverted())
                <div class="mt-5 px-4 py-3 rounded-lg bg-primary-fixed text-on-primary-fixed text-body-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">link</span>
                    Calon siswa ini telah dikonversi menjadi siswa. Data bersifat read-only.
                    @if($prospectiveStudent->convertedStudent)
                        <span class="mx-1">&bull;</span>
                        <a href="{{ route('siswa.show', $prospectiveStudent->convertedStudent) }}" wire:navigate class="inline-flex items-center gap-1 font-semibold underline decoration-1 underline-offset-2 hover:opacity-80">
                            Siswa: {{ $prospectiveStudent->convertedStudent->nama_lengkap }}
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-6">
        <div class="p-5 border-b border-outline-variant flex items-start justify-between gap-4">
            <div>
                <h2 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Pendaftaran</h2>
                <p class="text-body-sm text-on-surface-variant mt-1">Biaya pendaftaran calon siswa untuk tahun ajaran tujuan.</p>
            </div>
            @if(! $prospectiveStudent->isConverted())
                <button type="button" wire:click="openAddBill" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-primary font-label-md border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors shrink-0">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tambah Tagihan
                </button>
            @endif
        </div>
        @if($prospectiveStudent->bills->isNotEmpty())
            @include('livewire.prospective-student.bill-table', ['bills' => $prospectiveStudent->bills])
        @else
            <div class="p-10 text-center text-on-surface-variant">
                <span class="material-symbols-outlined text-4xl block mx-auto mb-2">receipt_long</span>
                <p>Belum ada tagihan pendaftaran.</p>
            </div>
        @endif
    </section>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-6">
        <div class="p-5 border-b border-outline-variant">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Pembayaran</h2>
            <p class="text-body-sm text-on-surface-variant mt-1">Riwayat transaksi pembayaran pendaftaran calon siswa.</p>
        </div>
        <div class="overflow-x-auto">
            @if($prospectiveStudent->payments->isNotEmpty())
                <table class="w-full text-left border-collapse min-w-[640px]">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container-low/60 text-label-md font-label-md text-on-surface-variant">
                            <th class="py-2.5 px-5 font-label-md">No. Kwitansi</th>
                            <th class="py-2.5 px-5 font-label-md">Tanggal</th>
                            <th class="py-2.5 px-5 font-label-md">Bank / Metode</th>
                            <th class="py-2.5 px-5 text-right font-label-md">Jumlah</th>
                            <th class="py-2.5 px-5 font-label-md">Status</th>
                            <th class="py-2.5 px-5 text-right font-label-md">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @foreach($prospectiveStudent->payments->sortByDesc('id') as $payment)
                            <tr wire:key="payment-{{ $payment->id }}" class="hover:bg-surface-container-low/40">
                                <td class="py-3.5 px-5 text-body-md font-semibold text-on-surface font-numeric-data whitespace-nowrap">{{ $payment->receipt_number }}</td>
                                <td class="py-3.5 px-5 text-body-md text-on-surface-variant whitespace-nowrap">{{ $payment->payment_date?->locale('id')->translatedFormat('d F Y') }}</td>
                                <td class="py-3.5 px-5 text-body-md text-on-surface">{{ $payment->bank?->paymentLabel() ?? '—' }}</td>
                                <td class="py-3.5 px-5 text-right text-body-md font-semibold text-on-surface font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $payment->total_amount, 0, ',', '.') }}</td>
                                <td class="py-3.5 px-5">
                                    @if($payment->isCancelled())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-error"></span>{{ $payment->status_label }}
                                        </span>
                                    @elseif($payment->status_label === 'Sebagian')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-yellow-100 text-yellow-800 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span>{{ $payment->status_label }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-on-tertiary-fixed whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-on-tertiary-fixed"></span>{{ $payment->status_label }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('pembayaran.prospective.show', $payment) }}" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Lihat Detail / Cetak Kwitansi"><span class="material-symbols-outlined text-[20px]">visibility</span></a>
                                        @if($payment->isActive())
                                            <a href="{{ route('pembayaran.prospective.edit', ['payment' => $payment->id]) }}" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit Pembayaran"><span class="material-symbols-outlined text-[20px]">edit</span></a>
                                        @endif
                                        <button type="button" wire:click="confirmDeletePayment({{ $payment->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus Transaksi"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-10 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl block mx-auto mb-2">receipt_long</span>
                    <p>Belum ada riwayat pembayaran.</p>
                </div>
            @endif
        </div>
    </section>

    @if($isDeletePaymentModalOpen && $deletingPayment)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-payment-title">
            <div class="absolute inset-0 bg-black/40" wire:click="cancelDeletePayment"></div>
            <div class="relative bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-5 border-b border-outline-variant flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-error-container flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-error text-[22px]">delete</span>
                    </div>
                    <div>
                        <h3 class="text-headline-sm font-headline-sm text-on-surface" id="delete-payment-title">Hapus transaksi ini secara permanen?</h3>
                        <p class="text-body-sm text-on-surface-variant mt-1">Pembayaran <strong class="font-numeric-data">{{ $deletingPayment->receipt_number }}</strong> untuk <strong>{{ $deletingPayment->prospectiveStudent->nama_lengkap ?? '—' }}</strong> beserta detail transaksinya akan dihapus. Nilai pembayaran pada tagihan pendaftaran calon siswa akan dikembalikan.</p>
                    </div>
                </div>
                <div class="px-6 py-4 bg-surface-container-low/60 border-t border-outline-variant flex justify-end gap-3">
                    <button type="button" wire:click="cancelDeletePayment" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button type="button" wire:click="deletePayment" class="px-5 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                        Hapus Permanen
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($isEditProfileOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" aria-labelledby="edit-profile-title" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <div>
                        <h3 class="text-headline-sm font-headline-sm text-on-surface" id="edit-profile-title">Edit Profil Calon Siswa</h3>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">{{ $prospectiveStudent->nama_lengkap }}</p>
                    </div>
                    <button type="button" wire:click="closeEditProfile" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <form wire:submit="saveProfile" class="flex flex-col gap-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="workspace-nama-lengkap" class="block text-label-md font-label-md text-on-surface mb-1">Nama Lengkap <span class="text-error">*</span></label>
                                <input type="text" id="workspace-nama-lengkap" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('nama_lengkap') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="workspace-nama-panggilan" class="block text-label-md font-label-md text-on-surface mb-1">Nama Panggilan</label>
                                <input type="text" id="workspace-nama-panggilan" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('nama_panggilan') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="workspace-academic-year-id" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran Tujuan <span class="text-error">*</span></label>
                                <select id="workspace-academic-year-id" wire:model="academic_year_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Pilih Tahun Ajaran --</option>
                                    @foreach($academicYears as $academicYear)
                                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                                    @endforeach
                                </select>
                                @error('academic_year_id') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="workspace-school-class-id" class="block text-label-md font-label-md text-on-surface mb-1">Kelas Tujuan <span class="text-error">*</span></label>
                                <select id="workspace-school-class-id" wire:model="school_class_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Belum Ditentukan --</option>
                                    @foreach($schoolClasses as $schoolClass)
                                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}{{ $schoolClass->school_level ? ' — Jenjang '.$schoolClass->school_level->value : '' }}</option>
                                    @endforeach
                                </select>
                                <p class="text-body-sm text-on-surface-variant mt-1">Jenjang calon siswa diturunkan dari kelas tujuan yang dipilih.</p>
                                @error('school_class_id') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="workspace-nama-ortu" class="block text-label-md font-label-md text-on-surface mb-1">Nama Orang Tua</label>
                                <input type="text" id="workspace-nama-ortu" wire:model="nama_orang_tua" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('nama_orang_tua') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="workspace-no-telp" class="block text-label-md font-label-md text-on-surface mb-1">No. HP Orang Tua</label>
                                <input type="text" id="workspace-no-telp" wire:model="no_telp_orang_tua" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('no_telp_orang_tua') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="workspace-alamat" class="block text-label-md font-label-md text-on-surface mb-1">Alamat Lengkap</label>
                            <textarea id="workspace-alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"></textarea>
                            @error('alamat') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="workspace-notes" class="block text-label-md font-label-md text-on-surface mb-1">Catatan</label>
                            <textarea id="workspace-notes" wire:model="notes" rows="2" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"></textarea>
                            @error('notes') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
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

    @if($isEditOpen && $editBillId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Edit Tagihan</h3>
                    <button wire:click="closeEdit" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex flex-col gap-6">
                    <p class="text-body-md text-on-surface font-medium">{{ $editTypeName }} <span class="text-on-surface-variant">- Tahun Ajaran {{ $editPeriodLabel }}</span></p>
                    <div>
                        <label for="workspace_edit_amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal Tagihan <span class="text-error">*</span></label>
                        <div class="relative"
                             x-data="{
                                 display: '{{ $editAmount ? number_format((int) $editAmount, 0, ',', '.') : '' }}',
                                 handleInput(e) {
                                     const raw = e.target.value.replace(/[^0-9]/g, '');
                                     const num = parseInt(raw) || 0;
                                     this.display = num ? num.toLocaleString('id-ID') : '';
                                     clearTimeout(this._t);
                                     this._t = setTimeout(() => $wire.set('editAmount', num), 300);
                                 }
                             }">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                            <input type="text" id="workspace_edit_amount" x-model="display" @input="handleInput($event)" inputmode="numeric" placeholder="0" class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                        </div>
                        @error('editAmount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div class="bg-surface-container-low border border-outline-variant rounded-xl divide-y divide-outline-variant">
                        <div class="flex justify-between px-4 py-2.5 text-body-md">
                            <span class="text-on-surface-variant">Sudah Dibayar</span>
                            <span class="text-tertiary font-semibold">Rp {{ number_format($editPaidAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between px-4 py-2.5 text-body-md">
                            <span class="text-on-surface-variant">Sisa</span>
                            <span class="{{ $editPaidAmount > 0 ? 'text-error' : 'text-on-surface-variant' }} font-semibold">Rp {{ number_format(max(0, (float) $editAmount - $editPaidAmount), 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeEdit" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="saveEditBill" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if($isAddOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Tambah Tagihan Manual</h3>
                    <button wire:click="closeAddBill" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="p-6 overflow-y-auto flex flex-col gap-4">
                    <div>
                        <label for="add_payment_type_id" class="block text-label-md font-label-md text-on-surface mb-1">Jenis Pembayaran <span class="text-error">*</span></label>
                        <select id="add_payment_type_id" wire:model.live="addPaymentTypeId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <option value="">Pilih jenis pembayaran</option>
                            @foreach ($prospectivePaymentTypes as $ptype)
                                <option value="{{ $ptype->id }}">{{ $ptype->name }}</option>
                            @endforeach
                        </select>
                        @if ($prospectivePaymentTypes->isEmpty())
                            <p class="text-body-sm text-on-surface-variant mt-1.5">Tidak ada jenis pembayaran yang tersedia untuk jenjang tujuan calon siswa ini.</p>
                        @endif
                        @error('addPaymentTypeId') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="add_amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal Tagihan <span class="text-error">*</span></label>
                        <div class="relative"
                             x-data="{
                                 display: '{{ $addAmount ? number_format((int) $addAmount, 0, ',', '.') : '' }}',
                                 handleInput(e) {
                                     const raw = e.target.value.replace(/[^0-9]/g, '');
                                     const num = parseInt(raw) || 0;
                                     this.display = num ? num.toLocaleString('id-ID') : '';
                                     clearTimeout(this._t);
                                     this._t = setTimeout(() => $wire.set('addAmount', num), 300);
                                 }
                             }">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                            <input type="text" id="add_amount" x-model="display" @input="handleInput($event)" inputmode="numeric" placeholder="0" class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                        </div>
                        @error('addAmount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="add_academic_year" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran <span class="text-error">*</span></label>
                        <select id="add_academic_year" wire:model="addAcademicYear" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <option value="">Pilih tahun ajaran</option>
                            @foreach ($addAcademicYearOptions as $ayOption)
                                <option value="{{ $ayOption }}">{{ $ayOption }}</option>
                            @endforeach
                        </select>
                        @error('addAcademicYear') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    <p class="text-body-sm text-on-surface-variant flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">info</span>Tagihan ini dibuat sebagai tagihan sekali bayar untuk tahun ajaran yang dipilih.</p>
                    @error('addPeriod') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeAddBill" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="saveAddBill" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if($isDeleteOpen && $deleteBillId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Tagihan</h3>
                    <button wire:click="closeDelete" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="p-6 overflow-y-auto flex flex-col gap-5">
                    <p class="text-body-md text-on-surface">Yakin ingin menghapus tagihan <strong>{{ $deleteTypeName }}</strong> <span class="text-on-surface-variant">(Tahun Ajaran {{ $deletePeriodLabel }})</span> senilai <strong>Rp {{ number_format($deleteAmount, 0, ',', '.') }}</strong>?</p>
                    <div class="bg-surface-container-low border border-outline-variant rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-body-sm">
                        <span class="text-on-surface-variant">Sudah Dibayar</span>
                        <span class="{{ $deletePaidAmount > 0 ? 'text-error' : 'text-on-surface' }} font-semibold">Rp {{ number_format($deletePaidAmount, 0, ',', '.') }}</span>
                    </div>
                    @if ($deletePaidAmount > 0)
                        <div class="bg-error-container border border-error text-on-error-container rounded-xl px-4 py-3 text-body-sm">Tagihan tidak dapat dihapus karena sudah memiliki pembayaran.</div>
                    @endif
                    @error('deleteConfirm') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeDelete" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="deleteBill" @disabled($deletePaidAmount > 0) class="{{ $deletePaidAmount > 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-error/90' }} bg-error text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Hapus Tagihan</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.prospective-student.convert-modal', ['prospectiveStudent' => $prospectiveStudent])
</div>