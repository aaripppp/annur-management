<div>
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-3">
        <a href="{{ route('pembayaran.index', ['student' => $student]) }}" wire:navigate class="hover:text-primary">Pembayaran</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">{{ $isEdit ? 'Edit Pembayaran Manual' : 'Pembayaran Manual' }}</span>
    </div>

    <div class="flex items-center gap-4 mb-6">
        <div class="w-11 h-11 rounded-xl bg-primary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-[26px]">post_add</span>
        </div>
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface leading-tight">{{ $isEdit ? 'Edit Pembayaran Manual Siswa' : 'Pembayaran Manual Siswa' }}</h1>
            <p class="text-body-md text-on-surface-variant mt-0.5">{{ $isEdit ? 'Perbaiki rincian pembayaran manual tanpa memengaruhi Billbook.' : 'Catat pembayaran di luar tagihan siswa tanpa memengaruhi Billbook.' }}</p>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 flex flex-col gap-6">
            <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-[20px]">list_alt</span>
                        <div><h2 class="text-headline-sm font-headline-sm">Rincian Pembayaran</h2><p class="text-body-sm text-on-surface-variant mt-0.5">Jenis dan nominal pembayaran diisi manual.</p></div>
                    </div>
                    <span class="inline-flex items-center justify-center min-w-7 h-7 px-2 rounded-full text-label-sm bg-primary-fixed text-on-primary-fixed">{{ count($items) }}</span>
                </div>

                <div class="flex flex-col" data-testid="student-manual-payment-items">
                    @error('items') <div class="mx-5 mt-4 px-4 py-3 rounded-lg bg-error-container text-on-error-container text-body-sm">{{ $message }}</div> @enderror

                    <div class="hidden md:grid md:grid-cols-[2.5rem_minmax(0,1fr)_14rem_2.5rem] gap-3 px-5 py-2.5 bg-surface-container-low/60 border-b border-outline-variant text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">
                        <span class="text-center">No.</span><span>Jenis Pembayaran</span><span>Nominal</span><span class="text-center">Aksi</span>
                    </div>

                    <div class="divide-y divide-outline-variant/60 px-5">
                        @foreach($items as $index => $item)
                            <div wire:key="student-manual-payment-item-{{ $itemKeys[$index] }}" data-testid="student-manual-payment-item-row" class="grid grid-cols-[2rem_minmax(0,1fr)_2.5rem] md:grid-cols-[2.5rem_minmax(0,1fr)_14rem_2.5rem] gap-x-3 gap-y-2 items-start py-3.5">
                                <div class="col-start-1 row-start-1 md:row-auto pt-2.5 text-center text-body-sm text-on-surface-variant font-numeric-data">{{ $index + 1 }}</div>
                                <div class="col-start-2 col-span-2 row-start-1 md:col-span-1 md:row-auto min-w-0">
                                    <label for="manual-item-description-{{ $index }}" class="block md:hidden text-label-sm font-label-sm text-on-surface-variant mb-1">Jenis Pembayaran</label>
                                    <input id="manual-item-description-{{ $index }}" type="text" wire:model.live.debounce.300ms="items.{{ $index }}.description" placeholder="Infaq" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    @error('items.'.$index.'.description') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-start-2 row-start-2 md:col-start-3 md:row-auto min-w-0">
                                    <label for="manual-item-amount-{{ $index }}" class="block md:hidden text-label-sm font-label-sm text-on-surface-variant mb-1">Nominal</label>
                                    <div class="relative" x-data="{
                                        display: '{{ $this->formatAmount($item['amount']) }}',
                                        updateAmount(event) {
                                            const raw = event.target.value.replace(/[^0-9]/g, '');
                                            const value = parseInt(raw) || 0;
                                            const itemIndex = event.target.dataset.itemIndex;
                                            this.display = value ? value.toLocaleString('id-ID') : '';
                                            $wire.set(`items.${itemIndex}.amount`, raw === '' ? '' : value);
                                        }
                                    }">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">Rp</span>
                                        <input id="manual-item-amount-{{ $index }}" data-item-index="{{ $index }}" type="text" x-model="display" @input="updateAmount($event)" inputmode="numeric" placeholder="500.000" class="w-full pl-10 pr-3 py-2.5 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm font-numeric-data text-right">
                                    </div>
                                    @error('items.'.$index.'.amount') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-start-3 row-start-2 md:col-start-4 md:row-auto flex justify-center pt-6 md:pt-1.5">
                                    @if(count($items) > 1)
                                        <button type="button" wire:click="removeItem({{ $index }})" class="p-1.5 rounded-lg text-on-surface-variant hover:text-error hover:bg-error/10" title="Hapus pembayaran"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-5 py-4 border-t border-outline-variant">
                        <button type="button" wire:click="addItem" class="w-full border border-dashed border-primary/50 text-primary hover:bg-primary-fixed/40 px-4 py-2.5 rounded-xl font-label-lg flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[19px]">add</span>Tambah Pembayaran</button>
                    </div>
                </div>
            </section>

            <section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-outline-variant bg-surface-container-low"><span class="material-symbols-outlined text-primary text-[20px]">account_balance</span><h2 class="text-headline-sm font-headline-sm">Informasi Pembayaran</h2></div>
                <div class="p-5 flex flex-col gap-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="manual-bank" class="block text-label-md font-label-md text-on-surface mb-1">Metode / Rekening <span class="text-error">*</span></label>
                            <select id="manual-bank" wire:model.live="bank_id" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                <option value="">-- Pilih Bank Aktif --</option>
                                @foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->optionLabel() }}</option>@endforeach
                            </select>
                            @error('bank_id') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                            @if($banks->isEmpty()) <p class="text-error text-body-sm mt-1">Belum ada Bank aktif.</p> @endif
                        </div>
                        <div>
                            <label for="manual-payment-date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Pembayaran <span class="text-error">*</span></label>
                            <input id="manual-payment-date" type="date" wire:model.live="payment_date" class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            @error('payment_date') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-label-md font-label-md text-on-surface mb-1">Bukti Pembayaran <span class="text-on-surface-variant">(Opsional)</span></label>
                        <label class="border-2 border-dashed border-outline-variant rounded-xl p-6 flex flex-col items-center justify-center text-center hover:bg-surface-container-low transition-colors cursor-pointer relative">
                            <input type="file" wire:model="proof" accept=".jpg,.jpeg,.png,.pdf" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                            <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center mb-2"><span class="material-symbols-outlined text-on-surface-variant">cloud_upload</span></div>
                            @if($proof)
                                <p class="text-body-md font-semibold text-primary">{{ $proof->getClientOriginalName() }}</p><p class="text-body-sm text-on-surface-variant">File baru siap diunggah</p>
                            @elseif($existingProof)
                                <p class="text-body-md font-semibold text-on-surface">Bukti transfer tersimpan</p><p class="text-body-sm text-on-surface-variant">Pilih file baru hanya jika ingin mengganti</p>
                            @else
                                <p class="text-body-md font-medium text-on-surface">Klik untuk memilih bukti pembayaran</p><p class="text-body-sm text-on-surface-variant mt-0.5">JPG, JPEG, PNG, atau PDF, maksimal 5 MB</p>
                            @endif
                        </label>
                        <div wire:loading wire:target="proof" class="text-body-sm text-primary mt-1">Mengunggah file...</div>
                        @error('proof') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="manual-notes" class="block text-label-md font-label-md text-on-surface mb-1">Catatan <span class="text-on-surface-variant">(Opsional)</span></label>
                        <textarea id="manual-notes" wire:model="notes" rows="3" placeholder="Catatan tambahan transaksi..." class="w-full py-2.5 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"></textarea>
                        @error('notes') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </section>
        </div>

        <aside class="lg:col-span-1 lg:sticky lg:top-24">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-outline-variant bg-surface-container-low"><span class="material-symbols-outlined text-primary text-[20px]">description</span><h3 class="text-headline-sm font-headline-sm">Ringkasan Pembayaran</h3></div>
                <div class="p-5 flex flex-col gap-5">
                    <div class="flex items-center gap-3 bg-surface-container-low p-3 rounded-xl border border-outline-variant">
                        <div class="w-10 h-10 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold shrink-0">{{ strtoupper(substr($student->nama_lengkap, 0, 2)) }}</div>
                        <div class="min-w-0"><p class="font-bold text-on-surface truncate">{{ $student->nama_lengkap }}</p><p class="text-body-sm text-on-surface-variant">NIS {{ $student->nis ?? '—' }} &bull; Kelas {{ $student->schoolClass->name ?? '—' }}</p></div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2"><span class="text-label-md font-semibold text-on-surface-variant">Pembayaran Dipilih</span><span class="inline-flex items-center justify-center min-w-7 h-7 px-2 rounded-full text-label-sm bg-primary-fixed text-on-primary-fixed">{{ count($items) }}</span></div>
                        <div class="flex flex-col">
                            @foreach($items as $index => $item)
                                <div wire:key="student-manual-summary-item-{{ $itemKeys[$index] }}" class="flex items-center justify-between gap-3 py-2 border-b border-outline-variant/50 last:border-b-0 text-body-sm"><span class="min-w-0 truncate text-on-surface">{{ trim($item['description']) !== '' ? $item['description'] : 'Pembayaran '.($index + 1) }}</span><span class="shrink-0 font-semibold text-on-surface whitespace-nowrap text-right font-numeric-data">Rp {{ $this->formatAmount($item['amount']) ?: '0' }}</span></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-4 border-t border-outline-variant">
                        <div class="flex items-center justify-between gap-4 mb-3"><span class="min-w-0 text-body-md font-bold text-on-surface">Total Pembayaran</span><span data-testid="student-manual-total-amount" class="shrink-0 whitespace-nowrap text-right text-headline-md font-bold text-primary font-numeric-data">Rp {{ number_format($this->totalAmount(), 0, ',', '.') }}</span></div>
                    </div>

                    @php($selectedBank = $banks->firstWhere('id', (int) $bank_id))
                    <div class="flex flex-col gap-2.5 pt-4 border-t border-outline-variant text-body-sm text-on-surface-variant">
                        <div class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px]">account_balance</span><span>Penerimaan: <strong class="text-on-surface">{{ $selectedBank?->optionLabel() ?? '-' }}</strong></span></div>
                        <div class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px]">calendar_today</span><span>Tanggal: <strong class="text-on-surface">{{ $payment_date ? \Carbon\Carbon::parse($payment_date)->locale('id')->translatedFormat('d F Y') : '-' }}</strong></span></div>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        <button type="submit" wire:loading.attr="disabled" wire:target="save,proof" @disabled($banks->isEmpty()) class="w-full py-2.5 bg-primary hover:bg-primary/90 disabled:opacity-50 text-on-primary font-label-lg rounded-xl flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">payments</span>{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pembayaran' }}</button>
                        <a href="{{ $cancelUrl }}" wire:navigate class="w-full py-2.5 text-center text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container">Batal</a>
                    </div>
                </div>
            </div>
        </aside>
    </form>
</div>
