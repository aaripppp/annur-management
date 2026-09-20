<div class="overflow-x-auto">
    <table class="w-full text-left border-collapse min-w-[900px]">
        <thead>
            <tr class="bg-surface-container-low border-b border-outline-variant">
                <th class="py-2.5 px-3 w-12 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center whitespace-nowrap">No.</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap min-w-[180px]">Jenis Pembayaran</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap min-w-[130px]">Tahun Ajaran</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right min-w-[120px]">Tagihan</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right min-w-[120px]">Sudah Dibayar</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right min-w-[120px]">Sisa</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-center min-w-[120px]">Status</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-center min-w-[140px]">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
            @foreach ($bills as $bill)
                <tr wire:key="workspace-bill-{{ $bill->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                    <td class="py-3.5 px-3 align-middle text-center text-body-md text-on-surface-variant font-numeric-data whitespace-nowrap">{{ $loop->iteration }}</td>
                    <td class="py-3.5 px-4 align-middle text-body-md font-body-md text-on-surface whitespace-nowrap min-w-[180px]">{{ $bill->paymentType->name ?? 'Formulir Pendaftaran' }}</td>
                    <td class="py-3.5 px-4 align-middle text-body-md text-on-surface-variant whitespace-nowrap min-w-[130px] font-numeric-data">{{ $bill->academic_year }}</td>
                    <td class="py-3.5 px-4 align-middle text-right font-numeric-data tabular-nums whitespace-nowrap text-on-surface font-semibold min-w-[120px]">Rp {{ number_format((float) $bill->amount, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 align-middle text-right font-numeric-data tabular-nums whitespace-nowrap text-tertiary min-w-[120px]">Rp {{ number_format((float) $bill->paid_amount, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 align-middle text-right font-numeric-data tabular-nums whitespace-nowrap {{ $bill->remaining_amount > 0 ? 'text-error' : 'text-on-surface-variant' }} min-w-[120px]">Rp {{ number_format((float) $bill->remaining_amount, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap min-w-[120px]">
                        @if ($bill->status === \App\Models\ProspectiveStudentBill::STATUS_PAID)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-tertiary-fixed text-on-tertiary-fixed">
                                <span class="w-1.5 h-1.5 rounded-full bg-on-tertiary-fixed"></span> {{ $bill->status_label }}
                            </span>
                        @elseif ($bill->status === \App\Models\ProspectiveStudentBill::STATUS_PARTIAL)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-yellow-100 text-yellow-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span> {{ $bill->status_label }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-error-container text-on-error-container">
                                <span class="w-1.5 h-1.5 rounded-full bg-error"></span> {{ $bill->status_label }}
                            </span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap min-w-[140px]">
                        <div class="inline-flex items-center justify-center gap-1">
                            <button wire:click="editBill({{ $bill->id }})" class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-lg text-primary hover:bg-primary-fixed/50 transition-colors font-label-sm whitespace-nowrap">
                                <span class="material-symbols-outlined text-[16px]">edit</span>
                                Edit
                            </button>
                            <button wire:click="confirmDeleteBill({{ $bill->id }})" class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-lg text-error hover:bg-error/10 transition-colors font-label-sm whitespace-nowrap">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>