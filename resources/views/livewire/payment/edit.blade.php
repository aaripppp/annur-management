<div>
    @php
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

        $editGroups = [];

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
        $editGroups = array_merge($editGroups, array_values($monthlyOrdered));

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
        $editGroups = array_merge($editGroups, $yearlyOrdered);

        if ($oneTimeBills) {
            $editGroups[] = [
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
        <span class="text-on-surface font-medium">Edit Pembayaran</span>
    </div>

    <!-- Page Header -->
    <div class="flex items-center gap-4 mb-6">
        <div class="w-11 h-11 rounded-xl bg-primary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-[26px]">edit</span>
        </div>
        <div class="min-w-0">
            <h1 class="text-display-sm font-display-sm text-on-surface leading-tight">Edit Pembayaran</h1>
            <p class="text-body-md text-on-surface-variant mt-0.5">{{ $payment->receipt_number }} &bull; {{ $payment->student->nama_lengkap ?? '—' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Left Column -->
        <div class="lg:col-span-2 flex flex-col gap-6">

            <!-- Informasi Siswa (Read Only) -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Informasi Siswa</h2>
                </div>
                <div class="p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[24px]">person</span>
                    </div>
                    <div>
                        <div class="text-headline-sm font-bold text-on-surface">{{ $payment->student->nama_lengkap ?? '—' }}</div>
                        <div class="text-body-md text-on-surface-variant">Kelas {{ $payment->student->schoolClass->name ?? '—' }} &bull; NIS: {{ $payment->student->nis ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- Rincian Pembayaran — Grouped Billbook Style -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Rincian Pembayaran</h2>
                    <p class="text-body-sm text-on-surface-variant mt-0.5">Centang tagihan yang akan dibayar. Ubah nominal atau hapus tagihan yang tidak perlu.</p>
                </div>

                <div class="p-4 flex flex-col gap-4">
                    @error('selectedBillIds')
                        <div class="px-4 py-3 rounded-lg bg-error-container text-on-error-container text-body-sm">
                            <span class="material-symbols-outlined text-[16px] align-middle mr-1">error</span>{{ $message }}
                        </div>
                    @enderror

                    @forelse($editGroups as $group)
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
                                <table class="w-full text-left border-collapse min-w-[640px]">
                                    <thead>
                                        <tr class="border-b border-outline-variant bg-surface-container-low/60 text-label-md font-label-md text-on-surface-variant">
                                            <th class="py-2.5 px-4 w-10 text-center font-label-md">No.</th>
                                            <th class="py-2.5 px-4 w-12 text-center font-label-md">Pilih</th>
                                            <th class="py-2.5 px-4 font-label-md">Jenis Pembayaran</th>
                                            <th class="py-2.5 px-4 text-right font-label-md">Tagihan</th>
                                            <th class="py-2.5 px-4 text-right font-label-md">Nominal Dibayar</th>
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
                                                        <span class="block text-body-sm text-on-surface-variant">Sekarang Rp {{ number_format((float) $bill['current_amount'], 0, ',', '.') }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-4 text-right text-body-md text-on-surface font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $bill['amount'], 0, ',', '.') }}</td>
                                                 <td class="py-2.5 px-4 text-right">
                                                     @if($isSelected)
                                                         @php
                                                             $payAmount = (int) round((float) ($selectedBillAmounts[$bill['id']] ?? 0));
                                                         @endphp
                                                          <div class="flex items-center justify-end gap-2" wire:key="amount-{{ $bill['id'] }}">
                                                              <div class="relative w-36"
                                                                   x-data="{
                                                                       display: '{{ $payAmount > 0 ? number_format($payAmount, 0, ',', '.') : '' }}',
                                                                       handleInput(e) {
                                                                           const raw = e.target.value.replace(/[^0-9]/g, '');
                                                                           const num = parseInt(raw) || 0;
                                                                           this.display = num ? num.toLocaleString('id-ID') : '';
                                                                           $wire.set('selectedBillAmounts.{{ $bill['id'] }}', num);
                                                                       }
                                                                   }">
                                                                 <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-sm text-on-surface-variant">Rp</span>
                                                                 <input type="text"
                                                                        x-model="display"
                                                                        @input="handleInput($event)"
                                                                        inputmode="numeric"
                                                                        placeholder="0"
                                                                        class="w-full pl-8 pr-2 py-1.5 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-sm font-semibold shadow-sm text-right">
                                                             </div>
                                                         </div>
                                                        @error('selectedBillAmounts.'.$bill['id'])
                                                            <span class="text-error text-body-sm block mt-1 text-right">{{ $message }}</span>
                                                        @enderror
                                                    @else
                                                        <span class="text-body-sm text-on-surface-variant">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="border border-dashed border-outline-variant rounded-xl px-6 py-10 text-center">
                            <span class="material-symbols-outlined text-4xl mb-2 block text-on-surface-variant">receipt_long</span>
                            <p class="text-body-md text-on-surface-variant">Tidak ada tagihan yang tersedia.</p>
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
                        <label for="payment_date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Pembayaran <span class="text-error">*</span></label>
                        <input type="date" id="payment_date" wire:model.live="payment_date" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                        @error('payment_date') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="bank_id" class="block text-label-md font-label-md text-on-surface mb-1">Metode / Rekening <span class="text-error">*</span></label>
                        <select id="bank_id" wire:model.live="bank_id" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->optionLabel() }}</option>
                            @endforeach
                        </select>
                        @error('bank_id') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="description" class="block text-label-md font-label-md text-on-surface mb-1">Deskripsi Pembayaran</label>
                        <input type="text" id="description" wire:model.live="description" placeholder="Contoh: Pembayaran SPP dan Jemputan Oktober" maxlength="500" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                        @error('description') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('pembayaran.show', $payment->id) }}" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">
                    Batal
                </a>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:loading.class="opacity-50 pointer-events-none" class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
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
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-body-md font-bold text-on-surface">Total Pembayaran</span>
                            <span class="text-headline-md font-bold text-primary font-numeric-data">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
