@props([
    'prospect' => null,
    'summary' => null,
])

@if ($prospect && $summary)
    @php
        $registrationStatus = $prospect->bill_status_label;
        $registrationBillStatus = $prospect->bill_status;
        $registrationSettled = $registrationBillStatus === \App\Models\ProspectiveStudentBill::STATUS_PAID;
        $registrationPartial = $registrationBillStatus === \App\Models\ProspectiveStudentBill::STATUS_PARTIAL;
    @endphp
    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-stack-lg" data-registration-history>
        <div class="px-5 py-4 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Riwayat Pendaftaran</h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $registrationSettled ? 'bg-tertiary-fixed text-on-tertiary-fixed' : ($registrationPartial ? 'bg-yellow-100 text-yellow-800' : 'bg-error-container text-on-error-container') }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $registrationSettled ? 'bg-on-tertiary-fixed' : ($registrationPartial ? 'bg-yellow-600' : 'bg-error') }}"></span>{{ $registrationStatus }}
                    </span>
                </div>
                <p class="text-body-sm text-on-surface-variant mt-0.5">No. Pendaftaran {{ $prospect->registration_number }} &bull; Tahun Ajaran Tujuan {{ $prospect->academicYear?->year ?? '-' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('pembayaran.prospective.workspace', $prospect) }}" wire:navigate class="inline-flex items-center gap-1.5 text-on-surface-variant font-label-md border border-outline-variant rounded-xl px-3.5 py-2 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                    Riwayat Pembayaran
                </a>
                @if ((float) $summary['remaining'] > 0)
                    <a href="{{ route('pembayaran.prospective.create', $prospect) }}" wire:navigate class="inline-flex items-center gap-1.5 text-on-primary bg-primary hover:bg-primary/90 font-label-md rounded-xl px-3.5 py-2 transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                        Bayar Formulir
                    </a>
                @endif
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-outline-variant/60">
            <div class="px-5 py-4">
                <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Total Tagihan Pendaftaran</p>
                <p class="text-body-lg font-semibold text-on-surface mt-1 font-numeric-data">Rp {{ number_format((float) $summary['total'], 0, ',', '.') }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Sudah Dibayar</p>
                <p class="text-body-lg font-semibold text-tertiary mt-1 font-numeric-data">Rp {{ number_format((float) $summary['paid'], 0, ',', '.') }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Sisa Tagihan</p>
                <p class="text-body-lg font-semibold {{ (float) $summary['remaining'] > 0 ? 'text-error' : 'text-on-surface-variant' }} mt-1 font-numeric-data">Rp {{ number_format((float) $summary['remaining'], 0, ',', '.') }}</p>
            </div>
        </div>
    </section>
@endif
