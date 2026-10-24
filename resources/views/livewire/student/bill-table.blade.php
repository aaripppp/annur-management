@php
    if ($applyMonthlyOrder ?? false) {
        $bills = \App\Support\BillDisplayOrder::sort($bills);
    }

    $paymentHistories = $bills->mapWithKeys(function (\App\Models\StudentBill $bill): array {
        $history = $bill->paymentDetails
            ->filter(fn (\App\Models\PaymentDetail $detail): bool => $detail->payment?->isActive() === true)
            ->sort(function (\App\Models\PaymentDetail $left, \App\Models\PaymentDetail $right): int {
                $createdAtComparison = ($left->payment->created_at?->getTimestamp() ?? 0)
                    <=> ($right->payment->created_at?->getTimestamp() ?? 0);

                if ($createdAtComparison !== 0) {
                    return $createdAtComparison;
                }

                $paymentIdComparison = $left->payment->id <=> $right->payment->id;

                return $paymentIdComparison !== 0
                    ? $paymentIdComparison
                    : $left->id <=> $right->id;
            })
            ->values();

        return [$bill->id => $history];
    });
@endphp
<div x-data="{ historyBillId: null }" @keydown.escape.window="historyBillId = null">
<div class="overflow-x-auto">
    <table class="w-full min-w-[1450px] text-left border-collapse">
        <thead>
            <tr class="bg-surface-container-low border-b border-outline-variant">
                <th class="py-2.5 px-3 w-12 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center whitespace-nowrap">No.</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap min-w-[150px]">Jenis Pembayaran</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap min-w-[110px]">Periode</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right min-w-[120px]">Tagihan</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right min-w-[120px]">Sudah Dibayar</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right min-w-[120px]">Sisa</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap min-w-[155px]">Tanggal TF Terakhir</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap min-w-[190px]">Tanggal Input Terakhir</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-center min-w-[100px]">Riwayat</th>
                <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-center min-w-[120px]">Status</th>
                @unless($readOnly ?? false)
                    <th class="py-2.5 px-4 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-center min-w-[140px]">Aksi</th>
                @endunless
            </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant">
            @foreach ($bills as $bill)
                @php
                    $periodLabel = $bill->period_label;
                    $paymentHistory = $paymentHistories->get($bill->id);
                    $latestPayment = $paymentHistory->last()?->payment;
                @endphp
                <tr wire:key="bill-{{ $bill->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                    <td class="py-3.5 px-3 align-middle text-center text-body-md text-on-surface-variant font-numeric-data whitespace-nowrap">{{ $loop->iteration }}</td>
                    <td class="py-3.5 px-4 align-middle text-body-md font-body-md text-on-surface whitespace-nowrap min-w-[150px]">{{ $bill->paymentType->name ?? '—' }}</td>
                    <td class="py-3.5 px-4 align-middle text-body-md text-on-surface-variant whitespace-nowrap min-w-[110px]">{{ $periodLabel }}</td>
                    <td class="py-3.5 px-4 align-middle text-right font-numeric-data tabular-nums whitespace-nowrap text-on-surface font-semibold min-w-[120px]">Rp {{ number_format((float) $bill->effective_amount, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 align-middle text-right font-numeric-data tabular-nums whitespace-nowrap text-tertiary min-w-[120px]">Rp {{ number_format((float) $bill->paid_amount, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 align-middle text-right font-numeric-data tabular-nums whitespace-nowrap {{ $bill->remaining_amount > 0 ? 'text-error' : 'text-on-surface-variant' }} min-w-[120px]">Rp {{ number_format((float) $bill->remaining_amount, 0, ',', '.') }}</td>
                    <td class="py-3.5 px-4 align-middle text-body-md text-on-surface-variant whitespace-nowrap min-w-[155px]">
                        {{ $latestPayment?->payment_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}
                    </td>
                    <td class="py-3.5 px-4 align-middle text-body-md text-on-surface-variant whitespace-nowrap min-w-[190px]">
                        {{ $latestPayment?->created_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}
                    </td>
                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap min-w-[100px]">
                        @if ($paymentHistory->isNotEmpty())
                            <button
                                type="button"
                                @click="historyBillId = {{ $bill->id }}"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-label-sm text-primary transition-colors hover:bg-primary-fixed/50"
                                aria-label="Lihat riwayat pembayaran {{ $bill->paymentType->name ?? 'tagihan' }} {{ $periodLabel }}"
                            >
                                <span class="material-symbols-outlined text-[16px]">history</span>
                                Lihat ({{ $paymentHistory->count() }})
                            </button>
                        @else
                            <span class="text-on-surface-variant">—</span>
                        @endif
                    </td>
                    <td class="py-3.5 px-4 align-middle text-center whitespace-nowrap min-w-[120px]">
                        @if ($bill->status === \App\Models\StudentBill::STATUS_PAID)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-tertiary-fixed text-on-tertiary-fixed">
                                <span class="w-1.5 h-1.5 rounded-full bg-on-tertiary-fixed"></span> Lunas
                            </span>
                        @elseif ($bill->status === \App\Models\StudentBill::STATUS_PARTIAL)
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-primary-fixed text-on-primary-fixed">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Sebagian
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap bg-error-container text-on-error-container">
                                <span class="w-1.5 h-1.5 rounded-full bg-error"></span> Belum Bayar
                            </span>
                        @endif
                    </td>
                    @unless($readOnly ?? false)
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
                    @endunless
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@foreach ($bills as $bill)
    @php
        $paymentHistory = $paymentHistories->get($bill->id);
    @endphp

    @if ($paymentHistory->isNotEmpty())
        <template x-teleport="body">
            <div
                x-cloak
                x-show="historyBillId === {{ $bill->id }}"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-on-surface/30 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="payment-history-title-{{ $bill->id }}"
                @click.self="historyBillId = null"
            >
                <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl" @click.stop>
                    <div class="flex items-start justify-between gap-4 border-b border-outline-variant bg-surface px-6 py-4">
                        <div>
                            <h3 id="payment-history-title-{{ $bill->id }}" class="text-headline-sm font-headline-sm text-on-surface">Riwayat Pembayaran</h3>
                            <p class="mt-0.5 text-body-sm text-on-surface-variant">
                                {{ $bill->paymentType->name ?? '—' }} &bull; {{ $bill->period_label }}
                            </p>
                        </div>
                        <button type="button" @click="historyBillId = null" class="rounded-lg p-1 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-error" aria-label="Tutup riwayat pembayaran">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="overflow-y-auto p-6">
                        <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <p class="text-label-md font-label-md text-on-surface-variant">Tagihan</p>
                                <p class="mt-1 font-numeric-data text-title-md font-semibold text-on-surface">Rp {{ number_format((float) $bill->effective_amount, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <p class="text-label-md font-label-md text-on-surface-variant">Sudah Dibayar</p>
                                <p class="mt-1 font-numeric-data text-title-md font-semibold text-tertiary">Rp {{ number_format((float) $bill->paid_amount, 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <p class="text-label-md font-label-md text-on-surface-variant">Sisa</p>
                                <p class="mt-1 font-numeric-data text-title-md font-semibold {{ $bill->remaining_amount > 0 ? 'text-error' : 'text-on-surface' }}">Rp {{ number_format((float) $bill->remaining_amount, 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-outline-variant">
                            <table class="w-full min-w-[760px] text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low">
                                        <th class="w-14 px-3 py-2.5 text-center text-label-sm font-label-sm uppercase tracking-wider text-on-surface-variant">No.</th>
                                        <th class="min-w-[150px] px-4 py-2.5 text-label-sm font-label-sm uppercase tracking-wider text-on-surface-variant">Tanggal TF</th>
                                        <th class="min-w-[190px] px-4 py-2.5 text-label-sm font-label-sm uppercase tracking-wider text-on-surface-variant">Tanggal Input</th>
                                        <th class="min-w-[130px] px-4 py-2.5 text-right text-label-sm font-label-sm uppercase tracking-wider text-on-surface-variant">Nominal</th>
                                        <th class="min-w-[170px] px-4 py-2.5 text-label-sm font-label-sm uppercase tracking-wider text-on-surface-variant">Kuitansi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @foreach ($paymentHistory as $paymentDetail)
                                        <tr class="hover:bg-surface-container-lowest/50">
                                            <td class="px-3 py-3 text-center font-numeric-data text-body-md text-on-surface-variant">{{ $loop->iteration }}</td>
                                            <td class="px-4 py-3 text-body-md text-on-surface-variant whitespace-nowrap">{{ $paymentDetail->payment->payment_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}</td>
                                            <td class="px-4 py-3 text-body-md text-on-surface-variant whitespace-nowrap">{{ $paymentDetail->payment->created_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right font-numeric-data font-semibold tabular-nums text-on-surface whitespace-nowrap">Rp {{ number_format((float) $paymentDetail->amount, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-body-md whitespace-nowrap">
                                                <a href="{{ route('pembayaran.show', $paymentDetail->payment->id) }}" class="font-medium text-primary transition-colors hover:underline">
                                                    {{ $paymentDetail->payment->receipt_number }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-outline-variant bg-surface px-6 py-4">
                        <button type="button" @click="historyBillId = null" class="rounded-xl border border-outline-variant px-5 py-2.5 font-label-lg text-on-surface-variant transition-colors hover:bg-surface-container">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif
@endforeach
</div>
