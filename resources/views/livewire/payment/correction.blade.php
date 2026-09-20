<div>
    @php
        // ==== Kelompokkan kandidat tagihan berdasarkan periode (UI only) ====
        $indonesianMonths = [
            'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4,
            'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agustus' => 8,
            'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12,
        ];

        $monthlyGroups = [];
        $yearlyGroups = [];
        $oneTimeBills = [];

        foreach ($candidateBills as $bill) {
            if ($bill['billing_frequency'] === 'one_time') {
                $oneTimeBills[] = $bill;
            } elseif ($bill['billing_frequency'] === 'yearly') {
                $yearlyGroups[$bill['period']][] = $bill;
            } else {
                $monthlyGroups[$bill['period']][] = $bill;
            }
        }

        $correctionGroups = [];

        $monthlyOrdered = [];
        foreach ($monthlyGroups as $label => $bills) {
            $parts = explode(' ', $label);
            $year = (int) end($parts);
            $month = $indonesianMonths[$parts[0]] ?? 0;
            $sortKey = $year * 12 + $month;
            $monthlyOrdered[$sortKey] = [
                'key' => 'monthly:'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                'label' => 'Tagihan '.$label,
                'type' => 'monthly',
                'bills' => $bills,
            ];
        }
        ksort($monthlyOrdered);
        $correctionGroups = array_merge($correctionGroups, array_values($monthlyOrdered));

        $yearlyOrdered = [];
        foreach ($yearlyGroups as $label => $bills) {
            $yearlyOrdered[] = [
                'key' => 'yearly:'.$label,
                'label' => $label,
                'type' => 'yearly',
                'bills' => $bills,
            ];
        }
        usort($yearlyOrdered, fn ($a, $b) => strcmp($b['key'], $a['key']));
        $correctionGroups = array_merge($correctionGroups, $yearlyOrdered);

        if ($oneTimeBills) {
            $correctionGroups[] = [
                'key' => 'one-time',
                'label' => 'Tagihan Sekali Bayar',
                'type' => 'one-time',
                'bills' => array_values($oneTimeBills),
            ];
        }
    @endphp

    <!-- Top Subtitle / Breadcrumb -->
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-3">
        <a href="{{ route('pembayaran.index') }}" class="hover:text-primary transition-colors">Pembayaran</a>
        <span>&rsaquo;</span>
        <a href="{{ route('pembayaran.show', $payment->id) }}" class="hover:text-primary transition-colors">{{ $payment->receipt_number }}</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">Koreksi Pembayaran</span>
    </div>

    <!-- Page Header -->
    <div class="flex items-center gap-4 mb-6">
        <div class="w-11 h-11 rounded-xl bg-primary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-[26px]">edit_note</span>
        </div>
        <div class="min-w-0">
            <h1 class="text-display-sm font-display-sm text-on-surface leading-tight">Koreksi Pembayaran</h1>
            <p class="text-body-md text-on-surface-variant mt-0.5">{{ $payment->receipt_number }} &bull; {{ $payment->student->nama_lengkap ?? '—' }}</p>
        </div>
    </div>

    <!-- Warning -->
    <div class="mb-6 px-4 py-3 rounded-xl bg-tertiary-fixed border border-tertiary/30 text-on-surface text-body-md flex items-start gap-3">
        <span class="material-symbols-outlined text-[20px] text-tertiary shrink-0">warning</span>
        <div>
            <strong>Pembayaran ini sudah tersimpan.</strong>
            Koreksi akan mengubah rincian transaksi dan saldo tagihan siswa.
        </div>
    </div>

    @if($step === 'edit')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- Left Column -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <!-- Transaksi Saat Ini -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                        <h2 class="text-headline-sm font-headline-sm text-on-surface">Transaksi Saat Ini</h2>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4 text-body-md">
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface-variant">Siswa</span>
                            <span class="block text-on-surface font-semibold mt-0.5">{{ $payment->student->nama_lengkap ?? '—' }}</span>
                            <span class="block text-body-sm text-on-surface-variant">{{ $payment->student->schoolClass->name ?? '—' }} | {{ $payment->student->nis ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface-variant">Tanggal</span>
                            <span class="block text-on-surface font-semibold mt-0.5">{{ $payment->payment_date->translatedFormat('d F Y') }}</span>
                        </div>
                        <div>
                            <span class="block text-label-md font-label-md text-on-surface-variant">Bank</span>
                            <span class="block text-on-surface font-semibold mt-0.5">{{ $payment->bank?->paymentLabel() ?? '—' }}</span>
                            @if($payment->bank?->displayAccountNumber())
                                <span class="block text-body-sm text-on-surface-variant font-numeric-data">{{ $payment->bank->displayAccountNumber() }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Pilih Rincian Baru -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                        <h2 class="text-headline-sm font-headline-sm text-on-surface">Rincian Pembayaran Baru</h2>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">Centang tagihan yang tetap dibayar. Kosongkan untuk menghapusnya dari transaksi.</p>
                    </div>

                    <div class="p-4 flex flex-col gap-4">
                        @error('selectedBillIds')
                            <div class="px-4 py-3 rounded-lg bg-error-container text-on-error-container text-body-sm">
                                <span class="material-symbols-outlined text-[16px] align-middle mr-1">error</span>{{ $message }}
                            </div>
                        @enderror

                        @forelse($correctionGroups as $group)
                            <div wire:key="group-{{ $group['key'] }}" class="rounded-xl border border-outline-variant overflow-hidden bg-surface-container-lowest">
                                <!-- Group Header -->
                                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-outline-variant bg-surface-container-low">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="material-symbols-outlined text-primary text-[20px] shrink-0">
                                            @if($group['type'] === 'yearly')
                                                event_repeat
                                            @elseif($group['type'] === 'one-time')
                                                workspace_premium
                                            @else
                                                calendar_month
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            @if($group['type'] === 'yearly')
                                                <h3 class="text-headline-sm font-headline-sm text-on-surface leading-tight">Tagihan Tahunan</h3>
                                                <p class="text-body-sm text-on-surface-variant truncate">{{ $group['label'] }}</p>
                                            @else
                                                <h3 class="text-headline-sm font-headline-sm text-on-surface leading-tight truncate">{{ $group['label'] }}</h3>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if($group['type'] === 'yearly')
                                            <span class="inline-flex items-center py-0.5 px-2 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-tertiary whitespace-nowrap">Tahunan</span>
                                        @elseif($group['type'] === 'one-time')
                                            <span class="inline-flex items-center py-0.5 px-2 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface-variant whitespace-nowrap">Sekali Bayar</span>
                                        @endif
                                        <span class="inline-flex items-center py-0.5 px-2 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface-variant whitespace-nowrap">
                                            {{ count($group['bills']) }} tagihan
                                        </span>
                                    </div>
                                </div>

                                <!-- Bill Table -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse min-w-[680px]">
                                        <thead>
                                            <tr class="border-b border-outline-variant bg-surface-container-low/60 text-label-md font-label-md text-on-surface-variant">
                                                <th class="py-2.5 px-4 w-10 text-center font-label-md">No.</th>
                                                <th class="py-2.5 px-4 w-12 text-center font-label-md">Pilih</th>
                                                <th class="py-2.5 px-4 font-label-md">Jenis Pembayaran</th>
                                                <th class="py-2.5 px-4 font-label-md">Periode</th>
                                                <th class="py-2.5 px-4 text-right font-label-md">Tagihan</th>
                                                <th class="py-2.5 px-4 text-right font-label-md">Sisa</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline-variant/60">
                                            @foreach($group['bills'] as $index => $bill)
                                                @php
                                                    $isSelected = in_array($bill['id'], $selectedIds);
                                                @endphp
                                                <tr wire:key="bill-row-{{ $bill['id'] }}" class="transition-colors {{ $isSelected ? 'bg-primary-fixed/30' : 'hover:bg-surface-container-low/60' }}">
                                                    <td class="py-2.5 px-4 text-center text-body-md text-on-surface-variant font-numeric-data">{{ $index + 1 }}</td>
                                                    <td class="py-2.5 px-4 text-center">
                                                        <input type="checkbox"
                                                               wire:model.live="selectedBillIds"
                                                               value="{{ $bill['id'] }}"
                                                               class="w-[18px] h-[18px] accent-primary rounded cursor-pointer shrink-0 align-middle"
                                                               title="Pilih {{ $bill['payment_type_name'] }}">
                                                    </td>
                                                    <td class="py-2.5 px-4">
                                                        <span class="block text-body-md text-on-surface {{ $isSelected ? 'font-semibold' : '' }}">{{ $bill['payment_type_name'] }}</span>
                                                        @if($bill['current_amount'] > 0)
                                                            <span class="block text-body-sm text-on-surface-variant">sekarang Rp {{ number_format((float) $bill['current_amount'], 0, ',', '.') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2.5 px-4 text-body-md text-on-surface-variant whitespace-nowrap">{{ $bill['period'] }}</td>
                                                    <td class="py-2.5 px-4 text-right text-body-md text-on-surface font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $bill['amount'], 0, ',', '.') }}</td>
                                                    <td class="py-2.5 px-4 text-right text-body-md font-semibold text-error font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $bill['remaining_amount'], 0, ',', '.') }}</td>
                                                </tr>

                                                @if($isSelected)
                                                    <tr wire:key="amount-row-{{ $bill['id'] }}" class="bg-surface-container-low/60">
                                                        <td colspan="6" class="px-4 py-2.5">
                                                            <div class="flex items-center gap-3 flex-wrap" wire:key="amount-{{ $bill['id'] }}">
                                                                <label class="text-label-sm font-label-sm text-on-surface-variant">Nominal koreksi</label>
                                                                @php
                                                                    $currentValue = (int) round((float) ($selectedBillAmounts[$bill['id']] ?? 0));
                                                                @endphp
                                                                <div class="relative w-44"
                                                                     x-data="{
                                                                         display: '{{ $currentValue > 0 ? number_format($currentValue, 0, ',', '.') : '' }}',
                                                                         handleInput(e) {
                                                                             const raw = e.target.value.replace(/[^0-9]/g, '');
                                                                             const num = parseInt(raw) || 0;
                                                                             this.display = num ? num.toLocaleString('id-ID') : '';
                                                                             $wire.set('selectedBillAmounts.{{ $bill['id'] }}', num);
                                                                         }
                                                                     }">
                                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                                                                    <input type="text"
                                                                           x-model="display"
                                                                           @input="handleInput($event)"
                                                                           inputmode="numeric"
                                                                           placeholder="0"
                                                                           class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                                                                </div>
                                                                <span class="text-body-sm text-on-surface-variant">Maks. Rp {{ number_format((float) $bill['max_amount'], 0, ',', '.') }}</span>
                                                                @error('selectedBillAmounts.'.$bill['id'])
                                                                    <span class="w-full text-error text-body-sm block">{{ $message }}</span>
                                                                @enderror
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-outline-variant rounded-xl px-6 py-10 text-center">
                                <p class="text-body-md text-on-surface-variant">Tidak ada tagihan yang dapat dikoreksi.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Informasi Pembayaran -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                        <h2 class="text-headline-sm font-headline-sm text-on-surface">Informasi Pembayaran</h2>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="bank_id" class="block text-label-md font-label-md text-on-surface mb-1">Metode / Rekening <span class="text-error">*</span></label>
                            <select id="bank_id" wire:model.live="bank_id" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->optionLabel() }}</option>
                                @endforeach
                            </select>
                            @error('bank_id') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="payment_date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Pembayaran <span class="text-error">*</span></label>
                            <input type="date" id="payment_date" wire:model.live="payment_date" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                            @error('payment_date') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('pembayaran.show', $payment->id) }}" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">
                        Batal
                    </a>
                    <button type="button" wire:click="gotoConfirm" class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">checklist</span>
                        Lanjut ke Konfirmasi
                    </button>
                </div>
            </div>

            <!-- Ringkasan -->
            <div class="lg:col-span-1 sticky top-24">
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                        <h3 class="text-headline-sm font-headline-sm text-on-surface">Ringkasan</h3>
                    </div>
                    <div class="p-5 flex flex-col gap-4">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-label-md font-semibold text-on-surface-variant">Tagihan Dipilih</span>
                                <span class="inline-flex items-center justify-center min-w-7 h-7 px-2 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">{{ count($selectedIds) }}</span>
                            </div>
                            <div class="flex flex-col">
                                @forelse($selectedIds as $billId)
                                    @php
                                        $selected = collect($candidateBills)->firstWhere('id', $billId);
                                        $amount = (int) round((float) ($selectedBillAmounts[$billId] ?? 0));
                                    @endphp
                                    @if($selected)
                                        <div wire:key="summary-{{ $billId }}" class="flex justify-between items-center gap-3 text-body-md py-2 border-b border-outline-variant/50 last:border-b-0">
                                            <span class="min-w-0 truncate text-on-surface">{{ $selected['payment_type_name'] }} <span class="text-body-sm text-on-surface-variant">({{ $selected['period'] }})</span></span>
                                            <span class="font-semibold text-on-surface whitespace-nowrap font-numeric-data">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                @empty
                                    <p class="text-body-sm text-on-surface-variant">Belum ada tagihan dipilih.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-4 border-t border-outline-variant">
                            <div class="flex justify-between items-center">
                                <span class="text-body-md font-bold text-on-surface">Total Pembayaran</span>
                                <span class="text-headline-md font-bold text-primary font-numeric-data">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Konfirmasi -->
        <div class="max-w-2xl mx-auto">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="px-6 py-5 border-b border-outline-variant bg-surface-container-low">
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Konfirmasi Koreksi</h2>
                </div>
                <div class="p-6 flex flex-col gap-5">
                    <div class="px-4 py-3 rounded-xl bg-tertiary-fixed border border-tertiary/30 text-on-surface text-body-md flex items-start gap-3">
                        <span class="material-symbols-outlined text-[20px] text-tertiary shrink-0">warning</span>
                        <span>Anda akan mengubah pembayaran <strong>{{ $payment->receipt_number }}</strong>. Perubahan akan memengaruhi saldo tagihan siswa.</span>
                    </div>

                    @if($errors->any())
                        <div class="px-4 py-3 rounded-lg bg-error-container text-on-error-container text-body-sm">
                            <span class="material-symbols-outlined text-[16px] align-middle mr-1">error</span>
                            Mohon periksa kembali data yang dimasukkan.
                            <ul class="mt-1 list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="bg-surface-container-low border border-outline-variant rounded-xl divide-y divide-outline-variant text-body-md">
                        <div class="flex justify-between px-4 py-2.5">
                            <span class="text-on-surface-variant">No. Kwitansi</span>
                            <span class="font-semibold text-on-surface font-numeric-data">{{ $payment->receipt_number }}</span>
                        </div>
                        <div class="flex justify-between px-4 py-2.5">
                            <span class="text-on-surface-variant">Siswa</span>
                            <span class="font-semibold text-on-surface">{{ $payment->student->nama_lengkap ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between px-4 py-2.5">
                            <span class="text-on-surface-variant">Total Pembayaran</span>
                            <span class="font-semibold text-on-surface font-numeric-data">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between px-4 py-2.5">
                            <span class="text-on-surface-variant">Tagihan Dipilih</span>
                            <span class="font-semibold text-on-surface">{{ count($selectedIds) }} tagihan</span>
                        </div>
                    </div>

                    <div>
                        <label for="reason" class="block text-label-md font-label-md text-on-surface mb-1">Alasan Koreksi <span class="text-error">*</span></label>
                        <textarea id="reason" wire:model.live="reason" rows="3" placeholder="Contoh: Salah centang tagihan OSIS." class="w-full py-2 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm"></textarea>
                        @error('reason') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button type="button" wire:click="backToEdit" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">
                            Kembali
                        </button>
                        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:loading.class="opacity-50 pointer-events-none" class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            <span wire:loading.remove wire:target="save">Simpan Koreksi</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
