<div>
    @unless($printMode)
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-stack-lg print:hidden">
            <div>
                <h1 class="text-display-sm font-display-sm text-on-surface">Detail Pembayaran Daycare</h1>
                <p class="text-body-md text-on-surface-variant mt-1">Lihat rincian transaksi dan cetak kwitansi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('daycare.payment.entry', ['tab' => 'history']) }}" wire:navigate class="px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">history</span>
                    Riwayat Transaksi
                </a>
                <a href="{{ route('daycare.show', $payment->child) }}" wire:navigate class="px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Kembali ke Detail Anak
                </a>
                <a href="{{ route('daycare.payment.edit', $payment) }}" wire:navigate class="px-5 py-2.5 text-primary font-label-lg border border-primary/40 rounded-xl hover:bg-primary-fixed/50 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                    Edit
                </a>
                <a href="{{ route('daycare.payment.print', $payment) }}" target="_blank" rel="noopener" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg flex items-center gap-2 shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">print</span>
                    Cetak Kwitansi
                </a>
                <a href="{{ route('daycare.payment.pdf', $payment) }}" class="px-5 py-2.5 text-primary font-label-lg border border-primary/40 rounded-xl hover:bg-primary-fixed/50 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Download PDF
                </a>
            </div>
        </div>

        @if(session()->has('success'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px] print:hidden">
                <span class="material-symbols-outlined text-secondary">check_circle</span>
                <p class="font-body-md">{{ session('success') }}</p>
            </div>
        @endif
    @endunless

    <article id="daycare-kwitansi-print-area" class="receipt-sheet w-[95%] max-w-none mx-auto bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-sm overflow-hidden print:border-none print:shadow-none print:m-0 print:w-full">
        <header class="receipt-header p-6 border-b border-outline-variant bg-surface-container-low/30">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/annur_logo2.png') }}" alt="Annur" class="receipt-brand-logo h-11 w-auto max-w-12 object-contain shrink-0">
                    <div><p class="receipt-brand-name text-label-md font-bold uppercase tracking-[0.18em] text-primary">Annur Management</p><h2 class="receipt-title text-display-sm font-bold text-on-surface mt-1">KWITANSI PEMBAYARAN</h2><p class="text-body-sm text-on-surface-variant mt-1">Kategori: Daycare</p></div>
                </div>
                <div class="sm:text-right">
                    <p class="receipt-label text-body-sm font-semibold uppercase tracking-wider text-on-surface-variant">No. Kwitansi</p>
                    <p class="receipt-number text-title-lg font-bold text-on-surface font-numeric-data mt-1 whitespace-nowrap">{{ $payment->receipt_number }}</p>
                    <span class="receipt-badge inline-flex items-center gap-1.5 mt-2 py-1 px-3 rounded-full text-label-sm bg-primary-fixed text-on-primary-fixed"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span>Pembayaran Daycare</span>
                    <p class="text-body-sm text-on-surface-variant mt-1">{{ $payment->payment_date->locale('id')->translatedFormat('d F Y') }}, {{ $payment->created_at->format('H:i') }}</p>
                </div>
            </div>
        </header>

        <section class="receipt-meta grid grid-cols-1 md:grid-cols-2 border-b border-outline-variant">
            <div class="receipt-meta-item p-6 flex items-start gap-4">
                <div class="receipt-meta-icon w-12 h-12 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center shrink-0"><x-receipt-icon name="person" class="w-6 h-6" /></div>
                <div><p class="text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">Informasi Anak</p><p class="receipt-meta-title text-headline-sm font-bold text-on-surface mt-1">{{ $payment->child->nama_lengkap }}</p><p class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5">Daycare &bull; Kelas {{ $payment->child->kelas }}</p></div>
            </div>
            <div class="receipt-meta-item receipt-payment-meta p-6 flex items-start justify-end gap-4 text-right">
                <div class="receipt-meta-icon w-12 h-12 rounded-full bg-secondary-fixed text-secondary flex items-center justify-center shrink-0"><x-receipt-icon name="bank" class="w-6 h-6" /></div>
                <div><p class="text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">Metode Pembayaran</p><p class="receipt-meta-title text-headline-sm font-bold text-on-surface mt-1">{{ $payment->bank->paymentLabel() }}</p>@if($payment->bank->isBank())<p class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5">{{ $payment->bank->account_number }}</p><p class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5">{{ $payment->bank->account_name }}</p>@endif</div>
            </div>
        </section>

        <section class="receipt-details p-6">
            <h3 class="receipt-details-heading text-label-md font-bold uppercase tracking-wider text-on-surface-variant mb-4">Rincian Pembayaran</h3>
            <table class="w-full text-left border-collapse">
                <thead><tr class="border-b border-outline-variant text-label-md text-on-surface-variant"><th class="py-3 px-2 w-14 text-center">No.</th><th class="py-3 px-2">Jenis Pembayaran</th><th class="py-3 px-2 text-right">Nominal</th></tr></thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @foreach($payment->details as $detail)
                        <tr><td class="py-3.5 px-2 text-center text-on-surface-variant font-numeric-data">{{ $loop->iteration }}</td><td class="py-3.5 px-2 font-medium text-on-surface">{{ $detail->description }}</td><td class="py-3.5 px-2 text-right font-bold text-on-surface font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $detail->amount, 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="border-t-2 border-outline-variant"><td colspan="2" class="py-4 px-2 text-right text-label-lg font-bold uppercase text-on-surface-variant">Total Pembayaran</td><td class="receipt-total py-4 px-2 text-right text-headline-md font-bold text-primary font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $payment->total_amount, 0, ',', '.') }}</td></tr></tfoot>
            </table>
        </section>

        @if(! $printMode && ($payment->notes || $payment->proof_path))
            <section class="px-6 pb-6 grid grid-cols-1 sm:grid-cols-2 gap-5 print:hidden">
                @if($payment->notes)<div><p class="text-body-sm text-on-surface-variant">Catatan</p><p class="text-body-md text-on-surface mt-1 whitespace-pre-line">{{ $payment->notes }}</p></div>@endif
                @if($payment->proof_path)<div><p class="text-body-sm text-on-surface-variant">Bukti Pembayaran</p><a href="{{ Storage::disk('public')->url($payment->proof_path) }}" target="_blank" class="text-primary hover:underline inline-flex items-center gap-1 mt-1"><span class="material-symbols-outlined text-[18px]">open_in_new</span>Lihat / Unduh Bukti</a></div>@endif
            </section>
        @endif

    </article>

    @include('components.receipt-print-styles')
</div>
