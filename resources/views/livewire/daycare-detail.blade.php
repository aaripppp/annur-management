<div>
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-4">
        <a href="{{ route('daycare.index') }}" wire:navigate class="hover:text-primary">Daycare</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">{{ $child->nama_lengkap }}</span>
    </div>

    @if(session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl px-5 py-5 sm:px-6 shadow-sm mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 lg:gap-6">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-14 h-14 rounded-full bg-surface-container-high border border-outline-variant text-on-surface-variant flex items-center justify-center font-bold text-title-lg shrink-0" aria-hidden="true">{{ strtoupper(substr($child->nama_lengkap, 0, 2)) }}</div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-headline-md font-headline-md text-on-surface leading-tight">{{ $child->nama_lengkap }}</h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $child->is_active ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' }}"><span class="w-1.5 h-1.5 rounded-full {{ $child->is_active ? 'bg-secondary' : 'bg-on-surface-variant' }}"></span>{{ $child->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <p class="text-body-sm text-on-surface-variant mt-1.5">{{ $child->nama_panggilan ?: '-' }} <span class="mx-1">&bull;</span> Kelas {{ $child->kelas ?: '-' }}</p>
                </div>
            </div>
            <button wire:click="openEditModal" class="px-3.5 py-2 text-primary font-label-md border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center justify-center gap-1.5 w-fit"><span class="material-symbols-outlined text-[18px]">edit</span>Edit Biodata</button>
        </div>

        <div class="mt-5 pt-5 border-t border-outline-variant">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4">
                <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="class">
                    <div class="w-9 h-9 rounded-lg bg-primary-fixed/60 text-primary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">school</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Kelas</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $child->kelas ?: '-' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:px-6" data-profile-field="birth">
                    <div class="w-9 h-9 rounded-lg bg-secondary-container/60 text-secondary flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">cake</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Tempat, Tanggal Lahir</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ collect([$child->tempat_lahir, $child->tanggal_lahir?->locale('id')->translatedFormat('d F Y')])->filter()->implode(', ') ?: '-' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:pl-6" data-profile-field="gender">
                    <div class="w-9 h-9 rounded-lg bg-tertiary-fixed/60 text-on-tertiary-fixed flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">person</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Jenis Kelamin</p><p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $child->jenis_kelamin === 'L' ? 'Laki-laki' : ($child->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-y-4 mt-4 pt-4 border-t border-outline-variant/60">
                <div class="flex items-start gap-3 min-w-0 lg:pr-6" data-profile-field="address">
                    <div class="w-9 h-9 rounded-lg bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">location_on</span></div>
                    <div class="min-w-0"><p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Alamat</p><p class="text-body-md font-semibold text-on-surface mt-0.5 whitespace-pre-line break-words">{{ $child->alamat ?: '-' }}</p></div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:px-6" data-profile-field="father">
                    <div class="w-9 h-9 rounded-lg bg-tertiary-fixed/60 text-on-tertiary-fixed flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">person</span></div>
                    <div class="min-w-0">
                        <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Ayah</p>
                        @if($child->nama_ayah && $child->no_telp_ayah)
                            <p class="text-body-md text-on-surface mt-0.5 break-words" data-parent-value="father">
                                <span class="font-semibold">{{ $child->nama_ayah }}</span>
                                <span class="mx-1 text-on-surface-variant">&bull;</span>
                                <span class="text-on-surface-variant font-numeric-data">{{ $child->no_telp_ayah }}</span>
                            </p>
                        @elseif($child->nama_ayah)
                            <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $child->nama_ayah }}</p>
                        @elseif($child->no_telp_ayah)
                            <p class="text-body-md font-medium text-on-surface-variant mt-0.5 font-numeric-data">{{ $child->no_telp_ayah }}</p>
                        @else
                            <p class="text-body-md font-semibold text-on-surface mt-0.5">-</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-start gap-3 min-w-0 lg:border-l lg:border-outline-variant/60 lg:pl-6" data-profile-field="mother">
                    <div class="w-9 h-9 rounded-lg bg-error-container/60 text-error flex items-center justify-center shrink-0" aria-hidden="true"><span class="material-symbols-outlined text-[19px]">person_2</span></div>
                    <div class="min-w-0">
                        <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Ibu</p>
                        @if($child->nama_ibu && $child->no_telp_ibu)
                            <p class="text-body-md text-on-surface mt-0.5 break-words" data-parent-value="mother">
                                <span class="font-semibold">{{ $child->nama_ibu }}</span>
                                <span class="mx-1 text-on-surface-variant">&bull;</span>
                                <span class="text-on-surface-variant font-numeric-data">{{ $child->no_telp_ibu }}</span>
                            </p>
                        @elseif($child->nama_ibu)
                            <p class="text-body-md font-semibold text-on-surface mt-0.5">{{ $child->nama_ibu }}</p>
                        @elseif($child->no_telp_ibu)
                            <p class="text-body-md font-medium text-on-surface-variant mt-0.5 font-numeric-data">{{ $child->no_telp_ibu }}</p>
                        @else
                            <p class="text-body-md font-semibold text-on-surface mt-0.5">-</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-3 gap-gutter mb-6">
        <div class="h-full min-h-28 bg-surface-container-lowest border border-outline-variant rounded-xl p-5 flex flex-col justify-between"><p class="text-body-md text-on-surface-variant">Total Transaksi</p><p class="text-headline-md font-headline-md text-on-surface mt-3 font-numeric-data">{{ number_format($totalTransactions, 0, ',', '.') }}</p></div>
        <div class="h-full min-h-28 bg-surface-container-lowest border border-outline-variant rounded-xl p-5 flex flex-col justify-between"><p class="text-body-md text-on-surface-variant">Total Pembayaran</p><p class="text-headline-md font-headline-md text-primary mt-3 font-numeric-data">Rp {{ number_format((float) $totalPayments, 0, ',', '.') }}</p></div>
        <div class="h-full min-h-28 bg-surface-container-lowest border border-outline-variant rounded-xl p-5 flex flex-col justify-between"><p class="text-body-md text-on-surface-variant">Pembayaran Terakhir</p><p class="text-headline-sm font-headline-sm text-on-surface mt-3">{{ $lastPayment ? $lastPayment->payment_date->locale('id')->translatedFormat('d F Y') : '-' }}</p></div>
    </section>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
        <div class="p-5 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div><h2 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Transaksi Biaya</h2><p class="text-body-sm text-on-surface-variant mt-1">Riwayat pembayaran {{ $child->nama_lengkap }}.</p></div>
            <a href="{{ route('daycare.payment.create', $child) }}" wire:navigate class="bg-primary hover:bg-primary/90 text-on-primary px-4 py-2.5 rounded-xl font-label-lg flex items-center gap-2 w-fit"><span class="material-symbols-outlined text-[19px]">add</span>Input Pembayaran</a>
        </div>
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead><tr class="bg-surface-container-low border-b border-outline-variant text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider"><th class="py-3.5 px-3 w-14 text-center">No.</th><th class="py-3 px-4">No. Kwitansi</th><th class="py-3 px-4">Tanggal TF</th><th class="py-3 px-4">Detail Pembayaran</th><th class="py-3 px-4">Bank</th><th class="py-3 px-4">Total Pembayaran</th><th class="py-3 px-4 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse($payments as $payment)
                        <tr wire:key="daycare-payment-{{ $payment->id }}" class="hover:bg-surface-container-low transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ ($payments->currentPage() - 1) * $payments->perPage() + $loop->iteration }}</td>
                            <td class="py-3 px-4 text-body-md font-bold text-on-surface whitespace-nowrap font-numeric-data">{{ $payment->receipt_number }}</td>
                            <td class="py-3 px-4 text-body-md text-on-surface-variant whitespace-nowrap">{{ $payment->payment_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td class="py-3 px-4 text-body-md text-on-surface-variant max-w-[240px] truncate">{{ $payment->detail_summary }}</td>
                            <td class="py-3 px-4 text-body-md text-on-surface whitespace-nowrap">
                                <div class="font-semibold">{{ $payment->bank->paymentLabel() }}</div>
                                @if($payment->bank->displayAccountNumber())
                                    <div class="text-body-sm text-on-surface-variant font-numeric-data">{{ $payment->bank->displayAccountNumber() }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-body-md font-bold text-on-surface whitespace-nowrap font-numeric-data">Rp {{ number_format((float) $payment->total_amount, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right whitespace-nowrap"><div class="flex items-center justify-end gap-1"><a href="{{ route('daycare.payment.show', $payment) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg" title="Lihat"><span class="material-symbols-outlined text-[20px]">visibility</span></a><a href="{{ route('daycare.payment.edit', $payment) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg" title="Edit"><span class="material-symbols-outlined text-[20px]">edit</span></a><button type="button" wire:click="confirmDelete({{ $payment->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg" title="Hapus"><span class="material-symbols-outlined text-[20px]">delete</span></button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-on-surface-variant"><span class="material-symbols-outlined text-4xl block mb-2">receipt_long</span>Belum ada transaksi biaya.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())<div class="p-4 border-t border-outline-variant">{{ $payments->links(data: ['scrollTo' => false]) }}</div>@endif
    </section>

    @if($isEditModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true"><div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]"><div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface"><h3 class="text-headline-sm font-headline-sm">Edit Biodata Daycare</h3><button type="button" wire:click="closeEditModal" class="text-on-surface-variant hover:text-error"><span class="material-symbols-outlined">close</span></button></div><form wire:submit="updateBiodata" class="contents"><div class="p-6 overflow-y-auto flex flex-col gap-5">@include('livewire.daycare-child-fields')</div><div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3"><button type="button" wire:click="closeEditModal" class="px-5 py-2.5 text-on-surface-variant rounded-xl">Batal</button><button type="submit" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg">Simpan Perubahan</button></div></form></div></div>
    @endif

    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="p-6"><div class="w-12 h-12 rounded-full bg-error-container text-error flex items-center justify-center mb-4"><span class="material-symbols-outlined">delete_forever</span></div><h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus transaksi {{ $deletingReceiptNumber }}?</h3><p class="text-body-md text-on-surface-variant mt-2">Transaksi akan dihapus permanen dan tidak lagi dihitung dalam Dashboard maupun rekap Bank.</p></div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3"><button type="button" wire:click="cancelDelete" class="px-5 py-2.5 text-on-surface-variant rounded-xl hover:bg-surface-container">Batal</button><button type="button" wire:click="delete" class="px-5 py-2.5 bg-error text-on-error rounded-xl font-label-lg hover:opacity-90">Hapus Permanen</button></div>
            </div>
        </div>
    @endif
</div>
