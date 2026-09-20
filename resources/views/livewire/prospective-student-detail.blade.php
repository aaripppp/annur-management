<div>
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-4">
        <a href="{{ route('calon-siswa.index') }}" wire:navigate class="hover:text-primary">Calon Siswa</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">{{ $prospectiveStudent->nama_lengkap }}</span>
    </div>

    @if(session()->has('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8" class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
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
                        <h1 class="text-headline-md font-headline-md text-on-surface leading-tight">{{ $prospectiveStudent->nama_lengkap }}</h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $prospectiveStudent->status->value === 'registered' ? 'bg-secondary-fixed text-on-secondary-fixed' : ($prospectiveStudent->status->value === 'converted' ? 'bg-primary-fixed text-on-primary-fixed' : 'bg-error-container text-on-error-container') }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $prospectiveStudent->status->value === 'registered' ? 'bg-secondary' : ($prospectiveStudent->status->value === 'converted' ? 'bg-primary' : 'bg-error') }}"></span>
                            {{ $prospectiveStudent->status_label }}
                        </span>
                    </div>
                    <p class="text-body-sm text-on-surface-variant mt-1.5 font-numeric-data">{{ $prospectiveStudent->registration_number }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
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
                <a href="{{ route('calon-siswa.index') }}" wire:navigate class="px-3.5 py-2 text-primary font-label-md border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center justify-center gap-1.5 w-fit">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Kembali
                </a>
            </div>
        </div>

        <div class="mt-5 pt-5 border-t border-outline-variant">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4">
                <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="academic-year">
                    <div class="w-9 h-9 rounded-lg bg-primary-fixed/60 text-primary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">calendar_today</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tahun Ajaran Tujuan</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $prospectiveStudent->academicYear?->year ?? '-' }}</p></div>
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
        <div class="p-5 border-b border-outline-variant">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Tagihan Pendaftaran</h2>
            <p class="text-body-sm text-on-surface-variant mt-1">Biaya pendaftaran calon siswa untuk tahun ajaran tujuan.</p>
        </div>
        <div class="divide-y divide-outline-variant">
            @forelse($prospectiveStudent->bills as $bill)
                <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-6">
                    <div class="min-w-0">
                        <p class="text-body-sm text-on-surface-variant">Jenis Pembayaran</p>
                        <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $bill->paymentType?->name ?? 'Formulir Pendaftaran' }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-body-sm text-on-surface-variant">Tahun Ajaran</p>
                        <p class="text-body-md font-semibold text-on-surface mt-0.5 font-numeric-data">{{ $bill->academic_year }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-body-sm text-on-surface-variant">Nominal</p>
                        <p class="text-body-md font-semibold text-on-surface mt-0.5 font-numeric-data">Rp {{ number_format((float) $bill->amount, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        @if($bill->status === \App\Models\ProspectiveStudentBill::STATUS_PAID)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-on-tertiary-fixed whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-on-tertiary-fixed"></span>{{ $bill->status_label }}
                            </span>
                        @elseif($bill->status === \App\Models\ProspectiveStudentBill::STATUS_PARTIAL)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-yellow-100 text-yellow-800 whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span>{{ $bill->status_label }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm bg-error-container text-on-error-container whitespace-nowrap">
                                <span class="w-1.5 h-1.5 rounded-full bg-error"></span>{{ $bill->status_label }}
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl block mx-auto mb-2">receipt_long</span>
                    @if($prospectiveStudent->target_level === null)
                        <p>Kelas tujuan belum ditentukan sehingga tagihan pendaftaran belum dapat dibuat.</p>
                    @else
                        <p>Belum ada tagihan pendaftaran. Pastikan jenis pembayaran "Formulir Pendaftaran" aktif untuk jenjang ini dan tarifnya tersedia pada master tarif.</p>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
        <div class="p-5 border-b border-outline-variant">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Pembayaran</h2>
            <p class="text-body-sm text-on-surface-variant mt-1">Riwayat pembayaran pendaftaran calon siswa.</p>
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
                            <tr class="hover:bg-surface-container-low/40">
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

    @include('livewire.prospective-student.convert-modal', ['prospectiveStudent' => $prospectiveStudent])
</div>