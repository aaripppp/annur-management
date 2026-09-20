@if($isEditOpen && $editBillId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                <h3 class="text-headline-sm font-headline-sm text-on-surface">Edit Tagihan</h3>
                <button wire:click="closeEdit" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex flex-col gap-6">
                <p class="text-body-md text-on-surface font-medium">{{ $editTypeName }} <span class="text-on-surface-variant">- {{ $editPeriodLabel }}</span></p>
                <div>
                    <label for="edit_amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal Tagihan <span class="text-error">*</span></label>
                    <div class="relative"
                         x-data="{
                             display: '{{ $editAmount ? number_format((int) $editAmount, 0, ',', '.') : '' }}',
                             handleInput(e) {
                                 const raw = e.target.value.replace(/[^0-9]/g, '');
                                 const num = parseInt(raw) || 0;
                                 this.display = num ? num.toLocaleString('id-ID') : '';
                                 clearTimeout(this._t);
                                 this._t = setTimeout(() => $wire.set('editAmount', num), 300);
                             }
                         }">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                        <input type="text" id="edit_amount" x-model="display" @input="handleInput($event)" inputmode="numeric" placeholder="0" class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                    </div>
                    @error('editAmount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="bg-surface-container-low border border-outline-variant rounded-xl divide-y divide-outline-variant">
                    <div class="flex justify-between px-4 py-2.5 text-body-md">
                        <span class="text-on-surface-variant">Sudah Dibayar</span>
                        <span class="text-tertiary font-semibold">Rp {{ number_format($editPaidAmount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between px-4 py-2.5 text-body-md">
                        <span class="text-on-surface-variant">Sisa</span>
                        <span class="{{ $editPaidAmount > 0 ? 'text-error' : 'text-on-surface-variant' }} font-semibold">Rp {{ number_format(max(0, (float) $editAmount - $editPaidAmount), 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                <button type="button" wire:click="closeEdit" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                <button type="button" wire:click="saveEditBill" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
            </div>
        </div>
    </div>
@endif

@if($isAddOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                <h3 class="text-headline-sm font-headline-sm text-on-surface">Tambah Tagihan Manual</h3>
                <button wire:click="closeAddBill" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="p-6 overflow-y-auto flex flex-col gap-4">
                <div>
                    <label for="add_payment_type_id" class="block text-label-md font-label-md text-on-surface mb-1">Jenis Pembayaran <span class="text-error">*</span></label>
                    <select id="add_payment_type_id" wire:model.live="addPaymentTypeId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                        <option value="">Pilih jenis pembayaran</option>
                        @foreach ($manualAddPaymentTypes as $ptype)
                            <option value="{{ $ptype->id }}">{{ $ptype->name }}</option>
                        @endforeach
                    </select>
                    @if ($manualAddPaymentTypes->isEmpty())
                        <p class="text-body-sm text-on-surface-variant mt-1.5">Tidak ada jenis pembayaran yang tersedia.</p>
                    @endif
                    @error('addPaymentTypeId') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="add_amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal Tagihan <span class="text-error">*</span></label>
                    <div class="relative"
                         x-data="{
                             display: '{{ $addAmount ? number_format((int) $addAmount, 0, ',', '.') : '' }}',
                             handleInput(e) {
                                 const raw = e.target.value.replace(/[^0-9]/g, '');
                                 const num = parseInt(raw) || 0;
                                 this.display = num ? num.toLocaleString('id-ID') : '';
                                 clearTimeout(this._t);
                                 this._t = setTimeout(() => $wire.set('addAmount', num), 300);
                             }
                         }">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-body-md text-on-surface-variant font-medium">Rp</span>
                        <input type="text" id="add_amount" x-model="display" @input="handleInput($event)" inputmode="numeric" placeholder="0" class="w-full pl-9 pr-3 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md font-semibold shadow-sm">
                    </div>
                    @error('addAmount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                </div>

                @if ($addFrequency === 'monthly')
                    @if ($addPeriodLocked && $addLockedMonth && $addLockedYear)
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-body-sm text-on-surface-variant">Periode</span>
                                <span class="text-body-md text-on-surface font-semibold font-numeric-data">{{ Carbon\Carbon::createFromDate($addLockedYear, $addLockedMonth, 1)->locale('id')->translatedFormat('F Y') }}</span>
                            </div>
                            <p class="text-body-sm text-on-surface-variant mt-1 flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">lock</span>Periode diambil otomatis dari bagian tagihan yang dipilih.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="add_month" class="block text-label-md font-label-md text-on-surface mb-1">Bulan <span class="text-error">*</span></label>
                                <select id="add_month" wire:model="addMonth" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    @foreach ($addMonthOptions as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('addMonth') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="add_year" class="block text-label-md font-label-md text-on-surface mb-1">Tahun <span class="text-error">*</span></label>
                                <select id="add_year" wire:model="addYear" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    @foreach ($addYearOptions as $yearOption)
                                        <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                                    @endforeach
                                </select>
                                @error('addYear') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endif
                @elseif ($addFrequency === 'yearly')
                    @if ($addPeriodLocked && $addLockedAcademicYear)
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-body-sm text-on-surface-variant">Tahun Ajaran</span>
                                <span class="text-body-md text-on-surface font-semibold font-numeric-data">{{ $addLockedAcademicYear }}</span>
                            </div>
                            <p class="text-body-sm text-on-surface-variant mt-1 flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">lock</span>Tahun ajaran diambil otomatis dari bagian tagihan yang dipilih.</p>
                        </div>
                    @else
                        <div>
                            <label for="add_academic_year" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran <span class="text-error">*</span></label>
                            <select id="add_academic_year" wire:model="addAcademicYear" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                <option value="">Pilih tahun ajaran</option>
                                @foreach ($addAcademicYearOptions as $ayOption)
                                    <option value="{{ $ayOption }}">{{ $ayOption }}</option>
                                @endforeach
                            </select>
                            @error('addAcademicYear') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                    @endif
                @else
                    <p class="text-body-sm text-on-surface-variant flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">info</span>Tagihan ini dibuat sebagai tagihan sekali bayar (tanpa periode bulanan/tahunan).</p>
                @endif

                @error('addPeriod') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
            </div>
            <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                <button type="button" wire:click="closeAddBill" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                <button type="button" wire:click="saveAddBill" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
            </div>
        </div>
    </div>
@endif

@if($isDeleteOpen && $deleteBillId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Tagihan</h3>
                <button wire:click="closeDelete" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="p-6 overflow-y-auto flex flex-col gap-5">
                <p class="text-body-md text-on-surface">Yakin ingin menghapus tagihan <strong>{{ $deleteTypeName }}</strong> <span class="text-on-surface-variant">({{ $deletePeriodLabel }})</span> senilai <strong>Rp {{ number_format($deleteAmount, 0, ',', '.') }}</strong>?</p>
                <div class="bg-surface-container-low border border-outline-variant rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-body-sm">
                    <span class="text-on-surface-variant">Sudah Dibayar</span>
                    <span class="{{ $deletePaidAmount > 0 ? 'text-error' : 'text-on-surface' }} font-semibold">Rp {{ number_format($deletePaidAmount, 0, ',', '.') }}</span>
                </div>
                @if ($deletePaidAmount > 0)
                    <div class="bg-error-container border border-error text-on-error-container rounded-xl px-4 py-3 text-body-sm">Tagihan tidak dapat dihapus karena sudah memiliki pembayaran.</div>
                @endif
                @error('deleteConfirm') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
            </div>
            <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                <button type="button" wire:click="closeDelete" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                <button type="button" wire:click="deleteBill" @disabled($deletePaidAmount > 0) class="{{ $deletePaidAmount > 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-error/90' }} bg-error text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Hapus Tagihan</button>
            </div>
        </div>
    </div>
@endif
