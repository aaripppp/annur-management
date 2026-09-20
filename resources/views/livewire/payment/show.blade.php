<div>
    @unless($printMode)
        <!-- Top Action Bar (Hidden when printing) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-stack-lg print:hidden">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Detail Pembayaran</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Lihat dan cetak rincian transaksi.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('pembayaran.index', ['tab' => 'history']) }}" class="px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Kembali
            </a>
            <a href="{{ route('pembayaran.index', ['student' => $payment->student_id]) }}" class="px-4 py-2.5 text-primary font-label-lg border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">person</span>
                Kembali ke Pembayaran Siswa
            </a>
            @if($payment->isActive())
                <a href="{{ $payment->isManualPayment() ? route('pembayaran.manual.edit', $payment) : route('pembayaran.edit', $payment) }}" class="px-4 py-2.5 text-primary font-label-lg border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Edit Pembayaran
                </a>
            @endif
            <a href="{{ route('pembayaran.print', $payment) }}" target="_blank" rel="noopener" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-[20px]">print</span>
                Cetak Kwitansi
            </a>
            <a href="{{ route('pembayaran.pdf', $payment) }}" class="px-5 py-2.5 text-primary font-label-lg border border-primary/40 rounded-xl hover:bg-primary-fixed/50 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">download</span>
                Download PDF
            </a>
        </div>
        </div>

        <!-- Toast Success if redirected from create -->
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
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px] print:hidden">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
            </div>
        @endif
    @endunless

    <!-- Kwitansi Printable Container -->
    <div id="kwitansi-print-area" class="receipt-sheet w-[95%] max-w-none mx-auto bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-sm overflow-hidden print:border-none print:shadow-none print:m-0 print:w-full">
        
        <!-- Header Kwitansi -->
        <div class="receipt-header p-6 border-b border-outline-variant flex flex-col sm:flex-row sm:items-start justify-between gap-5 bg-surface-container-low/30">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/annur_logo2.png') }}" alt="Annur" class="receipt-brand-logo h-11 w-auto max-w-12 object-contain shrink-0">
                <div>
                    <div class="receipt-brand-name text-label-md font-bold uppercase tracking-[0.18em] text-primary">Annur Management</div>
                    <div class="receipt-title text-display-sm font-bold text-on-surface mt-1">KWITANSI PEMBAYARAN</div>
                    <div class="text-body-sm text-on-surface-variant mt-1">Kategori: Siswa @if($payment->isManualPayment())<span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-800">Pembayaran Manual</span>@endif</div>
                </div>
            </div>
            <div class="flex flex-col sm:items-end gap-1 sm:text-right">
                <div class="receipt-label text-body-sm font-semibold uppercase tracking-wider text-on-surface-variant">No. Kwitansi</div>
                <div class="receipt-number text-title-lg font-bold text-on-surface font-numeric-data mt-1 whitespace-nowrap">{{ $payment->receipt_number }}</div>
                @if($payment->status_label === 'Dibatalkan')
                    <span class="receipt-badge inline-flex items-center gap-1.5 mt-2 py-1 px-3 rounded-full text-label-sm bg-error-container text-on-error-container w-fit">
                        <span class="w-2 h-2 rounded-full bg-error"></span>
                        Dibatalkan
                    </span>
                    @if($payment->cancellation_reason)
                        <span class="text-body-sm text-error mt-1 max-w-[280px] text-right">Alasan: {{ $payment->cancellation_reason }}</span>
                    @endif
                @elseif($payment->status_label === 'Tunggakan')
                    <span class="receipt-badge inline-flex items-center gap-1.5 mt-2 py-1 px-3 rounded-full text-label-sm bg-error-container text-on-error-container w-fit">
                        <span class="w-2 h-2 rounded-full bg-error"></span>
                        Tunggakan
                    </span>
                @else
                    <span class="receipt-badge inline-flex items-center gap-1.5 mt-2 py-1 px-3 rounded-full text-label-sm bg-primary-fixed text-on-primary-fixed w-fit">
                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                        {{ $payment->status_label }}
                    </span>
                @endif
                <span class="text-body-sm text-on-surface-variant mt-1 flex items-center gap-1">
                    <x-receipt-icon name="calendar" class="w-4 h-4" />
                    {{ $payment->payment_date ? $payment->payment_date->locale('id')->translatedFormat('d F Y') : '—' }}, {{ $payment->created_at->format('H:i') }}
                </span>
            </div>
        </div>

        <!-- Student & Payment Info -->
        <div class="receipt-meta grid grid-cols-1 md:grid-cols-2 border-b border-outline-variant">
            
            <!-- Student Info -->
            <div class="receipt-meta-item p-6 flex items-start gap-4">
                <div class="receipt-meta-icon w-12 h-12 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold text-headline-sm shrink-0">
                    <x-receipt-icon name="person" class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-label-md font-semibold text-on-surface-variant uppercase tracking-wider">Informasi Siswa</div>
                    <div class="receipt-meta-title text-headline-sm font-bold text-on-surface mt-1">{{ $payment->student->nama_lengkap ?? '—' }}</div>
                    <div class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5">
                        NIS {{ $payment->student->nis ?? '—' }} &bull; Kelas {{ $payment->student->schoolClass->name ?? '—' }}
                    </div>
                </div>
            </div>

            <div class="receipt-meta-item receipt-payment-meta p-6 flex items-start justify-end gap-4 text-right">
                <div class="receipt-meta-icon w-12 h-12 rounded-full bg-secondary-fixed text-secondary flex items-center justify-center font-bold text-headline-sm shrink-0">
                    <x-receipt-icon name="bank" class="w-6 h-6" />
                </div>
                <div>
                    <div class="text-label-md font-semibold text-on-surface-variant uppercase tracking-wider">Metode Pembayaran</div>
                    <div class="receipt-meta-title text-headline-sm font-bold text-on-surface mt-1">{{ $payment->bank->paymentLabel() }}</div>
                    @if($payment->bank->isBank())
                        <div class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5">{{ $payment->bank->account_number }}</div>
                        <div class="receipt-meta-copy text-body-md text-on-surface-variant mt-0.5">{{ $payment->bank->account_name }}</div>
                    @endif
                </div>
            </div>

        </div>

        <!-- Rincian Pembayaran Table -->
        <div class="receipt-details p-6">
            <div class="receipt-details-heading text-label-md font-bold uppercase tracking-wider text-on-surface-variant mb-4">Rincian Pembayaran</div>
            
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-outline-variant text-label-md text-on-surface-variant">
                        <th class="py-3 px-2 w-14 text-center">No.</th>
                        <th class="py-3 px-2">Deskripsi Item</th>
                        <th class="py-3 px-2 text-right">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/60">
                    @forelse($payment->details as $detail)
                        <tr>
                            <td class="py-3.5 px-2 text-center text-body-md text-on-surface-variant font-numeric-data">{{ $loop->iteration }}</td>
                            <td class="py-3.5 px-2 text-body-md text-on-surface font-medium">
                                {{ $payment->isManualPayment() ? $detail->description : ($detail->paymentType->name ?? 'Item Pembayaran') }}
                                @if($payment->isBillPayment() && $detail->description)
                                    <span class="text-on-surface-variant font-normal">({{ $detail->description }})</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-2 text-right text-body-md font-bold text-on-surface font-numeric-data">
                                {{ number_format($detail->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-3.5 px-2 text-center text-body-md text-on-surface-variant font-numeric-data">1</td>
                            <td class="py-3.5 px-2 text-body-md text-on-surface font-medium">
                                {{ $payment->description ?: 'Pembayaran SPP' }}
                            </td>
                            <td class="py-3.5 px-2 text-right text-body-md font-bold text-on-surface font-numeric-data">
                                {{ number_format($payment->total_amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-outline-variant">
                        <td colspan="2" class="py-4 px-2 text-right text-label-lg font-bold uppercase text-on-surface-variant">Total Pembayaran</td>
                        <td class="receipt-total py-4 px-2 text-right text-headline-md font-bold text-primary font-numeric-data">
                            Rp {{ number_format($payment->total_amount, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
            @if($payment->isManualPayment() && filled($payment->description))
                <div class="mt-4 rounded-lg bg-surface-container-low px-4 py-3 text-body-sm text-on-surface-variant"><strong class="text-on-surface">Catatan:</strong> {{ $payment->description }}</div>
            @endif
        </div>
    </div>

    @if($payment->isCancelled())
        <div class="receipt-cancellation px-6 py-4 bg-error-container/40 border border-error/20 rounded-xl flex items-center gap-3 text-body-md text-on-error-container">
            <x-receipt-icon name="cancel" class="w-5 h-5 shrink-0" />
            <div>
                <strong>Pembayaran dibatalkan</strong> pada {{ $payment->cancelled_at?->translatedFormat('d F Y, H:i') }}
                oleh {{ $payment->cancelledBy->name ?? 'Administrator' }}.
                <span class="block text-body-sm mt-0.5">{{ $payment->isManualPayment() ? 'Transaksi tidak lagi dihitung sebagai pemasukan aktif dan tidak mengubah tagihan siswa.' : 'Saldo tagihan terkait telah dikembalikan.' }}</span>
            </div>
        </div>
    @endif

    <!-- Cancel Modal -->
    @if(! $printMode && $showCancelModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 print:hidden">
            <div class="absolute inset-0 bg-black/40" wire:click="closeCancelModal"></div>
            <div class="relative bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-5 border-b border-outline-variant flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-error-container flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-error text-[22px]">cancel</span>
                    </div>
                    <div>
                        <h3 class="text-headline-sm font-headline-sm text-on-surface">Batalkan Pembayaran</h3>
                        <p class="text-body-sm text-on-surface-variant mt-1">@if($payment->isManualPayment()) Transaksi pembayaran manual <strong class="font-numeric-data">{{ $payment->receipt_number }}</strong> akan dibatalkan dan tidak lagi dihitung sebagai pemasukan aktif. Tagihan siswa tidak akan berubah. @else Pembayaran <strong class="font-numeric-data">{{ $payment->receipt_number }}</strong> akan dibatalkan dan saldo tagihan siswa dikembalikan. @endif Tindakan ini dicatat pada riwayat audit.</p>
                    </div>
                </div>
                <div class="px-6 py-5">
                    <label for="cancelReason" class="block text-label-md font-label-md text-on-surface mb-1.5">Alasan Pembatalan <span class="text-error">*</span></label>
                    <textarea id="cancelReason" wire:model="cancelReason" rows="3" placeholder="Contoh: Transfer dibatalkan oleh wali." class="w-full py-2 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm"></textarea>
                    @error('cancelReason') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="px-6 py-4 bg-surface-container-low/60 border-t border-outline-variant flex justify-end gap-3">
                    <button type="button" wire:click="closeCancelModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">
                        Tutup
                    </button>
                    <button type="button" wire:click="cancelPayment" class="px-5 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">cancel</span>
                        Ya, Batalkan
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('components.receipt-print-styles')
</div>
