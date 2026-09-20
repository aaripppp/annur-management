<div>
    <!-- Top Subtitle / Breadcrumb -->
    <div class="flex items-center gap-2 text-body-md text-on-surface-variant mb-3">
        <a href="{{ route('pembayaran.index') }}" class="hover:text-primary transition-colors">Pembayaran</a>
        <span>&rsaquo;</span>
        <span class="text-on-surface font-medium">Tambah Pembayaran</span>
    </div>

    <!-- Compact Page Header -->
    <div class="flex items-center gap-4 mb-6">
        <div class="w-11 h-11 rounded-xl bg-primary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-primary text-[26px]">receipt_long</span>
        </div>
        <div class="min-w-0">
            <h1 class="text-display-sm font-display-sm text-on-surface leading-tight">Pilih Tagihan yang Akan Dibayar</h1>
            <p class="text-body-md text-on-surface-variant mt-0.5">Centang satu atau beberapa tagihan. Setiap tagihan bisa dibayar penuh atau sebagian.</p>
        </div>
    </div>

    <!-- Main Form Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Left Column: Form Cards (2 Cols) -->
        <div class="lg:col-span-2 flex flex-col gap-6">

            <!-- Card 1: Data Siswa -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <span class="material-symbols-outlined text-primary text-[20px]">person_search</span>
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Data Siswa</h2>
                </div>

                <div class="p-5">
                    @if(!$selectedStudentData)
                        <label class="block text-label-md font-label-md text-on-surface mb-1">Cari Siswa</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                            <input type="text" wire:model.live.debounce.300ms="student_search" placeholder="Ketik nama siswa atau NIS..." class="w-full pl-10 pr-4 py-2.5 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                        </div>

                        <!-- Live Search Results Dropdown -->
                        @if(count($searchResults) > 0)
                            <div class="mt-2 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-xl overflow-hidden divide-y divide-outline-variant">
                                @foreach($searchResults as $student)
                                    <button type="button" wire:key="payment-create-student-result-{{ $student->id }}" wire:click="selectStudent({{ $student->id }})" class="w-full px-4 py-3 text-left hover:bg-surface-container-low transition-colors flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold text-body-sm">
                                                {{ strtoupper(substr($student->nama_lengkap, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-on-surface text-body-md">{{ $student->nama_lengkap }}</div>
                                                <div class="text-body-sm text-on-surface-variant">NIS: {{ $student->nis ?? '—' }} &bull; {{ $student->academicClassLabel() }} &bull; {{ $student->schoolLevel?->value ?? '—' }}</div>
                                                <div class="text-body-sm text-on-surface-variant">{{ $student->academic_status_label }} &bull; {{ $student->academicYearContextLabel() ?? '—' }}</div>
                                            </div>
                                        </div>
                                        <span class="material-symbols-outlined text-primary text-[20px]">add_circle</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(strlen($student_search) >= 2)
                            <div class="mt-2 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-xl p-4 text-center text-on-surface-variant text-body-md">
                                Siswa tidak ditemukan.
                            </div>
                        @endif
                    @else
                        <!-- Selected Student: compact identity -->
                        <div class="flex items-center justify-between gap-4 bg-primary-container/30 border border-primary/20 rounded-xl p-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-11 h-11 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold text-body-lg shrink-0">
                                    {{ strtoupper(substr($selectedStudentData['nama_lengkap'], 0, 2)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-on-surface text-body-lg truncate">{{ $selectedStudentData['nama_lengkap'] }}</div>
                                    <div class="text-body-sm text-on-surface-variant flex items-center gap-2 mt-0.5">
                                        <span class="whitespace-nowrap">NIS: {{ $selectedStudentData['nis'] ?? '—' }}</span>
                                        <span>&bull;</span>
                                        <span class="truncate">{{ $selectedStudentData['class_name'] }} &bull; {{ $selectedStudentData['school_level'] }} &bull; {{ $selectedStudentData['academic_status'] }} &bull; {{ $selectedStudentData['academic_year'] }}</span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" wire:click="clearStudent" class="flex items-center gap-1.5 shrink-0 text-on-surface-variant border border-outline-variant rounded-lg px-2.5 py-1.5 hover:text-error hover:bg-error/10 hover:border-error/40 transition-colors text-label-md" title="Ganti Siswa">
                                <span class="material-symbols-outlined text-[18px]">close</span>
                                Ganti
                            </button>
                        </div>
                    @endif

                    @error('selected_student_id')
                        <span class="text-error text-body-sm mt-1.5 block">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <!-- Card 2: Pilih Tagihan -->
            @if($selectedStudentData)
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                        <h2 class="text-headline-sm font-headline-sm text-on-surface">Tagihan yang Belum Lunas</h2>
                        <p class="text-body-sm text-on-surface-variant mt-0.5">Pilih satu atau beberapa tagihan untuk dibayar dalam satu transaksi.</p>
                    </div>

                    <div class="p-4 flex flex-col gap-4">
                        @error('selectedBillIds')
                            <div class="px-4 py-3 rounded-lg bg-error-container text-on-error-container text-body-sm">
                                <span class="material-symbols-outlined text-[16px] align-middle mr-1">error</span>{{ $message }}
                            </div>
                        @enderror

                        @forelse($billGroups as $group)
                            <div wire:key="group-{{ $group['key'] }}" class="rounded-xl border border-outline-variant overflow-hidden">
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
                                                <th class="py-2.5 px-4 text-right font-label-md whitespace-nowrap">Tagihan</th>
                                                <th class="py-2.5 px-4 text-right font-label-md whitespace-nowrap">Nominal Dibayar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline-variant/60">
                                            @foreach($group['bills'] as $index => $bill)
                                                @php
                                                    $isSelected = in_array($bill['id'], $selectedIds);
                                                    $payAmount = $isSelected
                                                        ? (int) round((float) ($selectedBillAmounts[$bill['id']] ?? $bill['remaining_amount']))
                                                        : 0;
                                                @endphp
                                                <tr wire:key="bill-row-{{ $bill['id'] }}" class="transition-colors {{ $isSelected ? 'bg-primary-fixed/30' : 'hover:bg-surface-container-low/60' }}">
                                                    <td class="py-2.5 px-4 text-center text-body-md text-on-surface-variant font-numeric-data whitespace-nowrap">{{ $index + 1 }}</td>
                                                    <td class="py-2.5 px-4 text-center whitespace-nowrap">
                                                        <input type="checkbox"
                                                               wire:model.live="selectedBillIds"
                                                               value="{{ $bill['id'] }}"
                                                               class="w-[18px] h-[18px] accent-primary rounded cursor-pointer shrink-0 align-middle"
                                                               title="Pilih {{ $bill['payment_type_name'] }}">
                                                    </td>
                                                    <td class="py-2.5 px-4">
                                                        <span class="block text-body-md text-on-surface {{ $isSelected ? 'font-semibold' : '' }}">{{ $bill['payment_type_name'] }}</span>
                                                        <span class="block text-body-sm text-on-surface-variant whitespace-nowrap">Total tagihan Rp {{ number_format((float) $bill['amount'], 0, ',', '.') }}</span>
                                                    </td>
                                                    <td class="py-2.5 px-4 text-right text-body-md text-error font-semibold font-numeric-data whitespace-nowrap">Rp {{ number_format((float) $bill['remaining_amount'], 0, ',', '.') }}</td>
                                                    <td class="py-2.5 px-4 text-right whitespace-nowrap">
                                                        @if($isSelected)
                                                            <div class="flex items-center justify-end" wire:key="amount-{{ $bill['id'] }}">
                                                                <div class="relative w-36"
                                                                     x-data="{
                                                                         display: '{{ $payAmount > 0 ? number_format($payAmount, 0, ',', '.') : '' }}',
                                                                         handleInput(e) {
                                                                             const raw = e.target.value.replace(/[^0-9]/g, '');
                                                                             const num = parseInt(raw) || 0;
                                                                             this.display = num ? num.toLocaleString('id-ID') : '';
                                                                             clearTimeout(this._t);
                                                                             this._t = setTimeout(() => $wire.set('selectedBillAmounts.{{ $bill['id'] }}', num), 300);
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
                                <span class="material-symbols-outlined text-4xl mb-2 block text-on-surface-variant">check_circle</span>
                                <p class="text-body-md text-on-surface font-medium">Tidak ada tagihan yang perlu dibayar.</p>
                                <p class="text-body-sm text-on-surface-variant mt-1">Semua tagihan siswa telah lunas.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            <!-- Card 3: Informasi Pembayaran -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <span class="material-symbols-outlined text-primary text-[20px]">account_balance</span>
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Informasi Pembayaran</h2>
                </div>

                <div class="p-5 flex flex-col gap-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
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

                    <!-- Bukti Pembayaran (Opsional Upload) -->
                    <div>
                        <label class="block text-label-md font-label-md text-on-surface mb-1">Bukti Pembayaran <span class="text-on-surface-variant">(Opsional)</span></label>
                        <div class="border-2 border-dashed border-outline-variant rounded-xl p-6 flex flex-col items-center justify-center text-center hover:bg-surface-container-low transition-colors cursor-pointer relative">
                            <input type="file" wire:model="receipt_file" accept="image/*,.pdf" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                            <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center mb-2">
                                <span class="material-symbols-outlined text-on-surface-variant text-[24px]">cloud_upload</span>
                            </div>
                            @if($receipt_file)
                                <div class="text-body-md font-semibold text-primary">{{ $receipt_file->getClientOriginalName() }}</div>
                                <div class="text-body-sm text-on-surface-variant">File siap diunggah</div>
                            @else
                                <div class="text-body-md font-medium text-on-surface">Klik untuk upload atau drag and drop</div>
                                <div class="text-body-sm text-on-surface-variant mt-0.5">SVG, PNG, JPG atau PDF (Max. 5MB)</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-label-md font-label-md text-on-surface mb-1">Catatan / Deskripsi <span class="text-on-surface-variant">(Opsional)</span></label>
                        <input type="text" id="description" wire:model.live.debounce.300ms="description" placeholder="Contoh: Titipan pembayaran / Catatan khusus..." class="w-full py-2 px-3 border-outline-variant focus:border-primary focus:ring-primary rounded-lg text-body-md shadow-sm">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Ringkasan Pembayaran (Sticky) -->
        <div class="lg:col-span-1 sticky top-24">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm flex flex-col">

                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-outline-variant bg-surface-container-low">
                    <span class="material-symbols-outlined text-primary text-[20px]">description</span>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Ringkasan Pembayaran</h3>
                </div>

                <div class="p-5 flex flex-col gap-5">
                    <!-- Student Preview -->
                    @if($selectedStudentData)
                        <div class="flex items-center gap-3 bg-surface-container-low p-3 rounded-xl border border-outline-variant">
                            <div class="w-10 h-10 rounded-full bg-primary-fixed text-on-primary-fixed flex items-center justify-center font-bold text-body-md shrink-0">
                                {{ strtoupper(substr($selectedStudentData['nama_lengkap'], 0, 2)) }}
                            </div>
                            <div class="overflow-hidden">
                                <div class="font-bold text-on-surface text-body-md truncate">{{ $selectedStudentData['nama_lengkap'] }}</div>
                                <div class="text-body-sm text-on-surface-variant truncate">Kelas {{ $selectedStudentData['class_name'] }} | {{ $selectedStudentData['nis'] }}</div>
                            </div>
                        </div>
                    @else
                        <div class="p-3.5 text-center text-body-sm text-on-surface-variant bg-surface-container-low rounded-xl border border-dashed border-outline-variant">
                            Belum ada siswa dipilih.
                        </div>
                    @endif

                    <!-- Tagihan Dipilih -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-label-md font-semibold text-on-surface-variant">Tagihan Dipilih</span>
                            <span class="inline-flex items-center justify-center min-w-7 h-7 px-2 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">{{ count($selectedIds) }}</span>
                        </div>
                        <div class="flex flex-col">
                            @forelse($selectedIds as $billId)
                                @php
                                    $selected = collect($outstandingBills)->firstWhere('id', $billId);
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

                    <!-- Total Pembayaran -->
                    <div class="pt-4 border-t border-outline-variant">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-body-md font-bold text-on-surface">Total Pembayaran</span>
                            <span class="text-headline-md font-bold text-primary font-numeric-data">Rp {{ number_format($totalPembayaran, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Transaction Preview Info -->
                    <div class="flex flex-col gap-2.5 pt-4 border-t border-outline-variant text-body-sm text-on-surface-variant">
                        @php
                            $selectedBank = $banks->firstWhere('id', $this->bank_id);
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">account_balance</span>
                            <span>Penerimaan: <strong class="text-on-surface">{{ $selectedBank?->optionLabel() ?? '—' }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
                            <span>Tanggal: <strong class="text-on-surface">{{ $payment_date ? \Carbon\Carbon::parse($payment_date)->translatedFormat('d F Y') : '—' }}</strong></span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex flex-col gap-2.5 pt-2">
                        <button type="button" wire:click="save" class="w-full py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">payments</span>
                            Simpan Pembayaran
                        </button>
                        <a href="{{ route('pembayaran.index') }}" class="w-full py-2.5 text-center text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">
                            Batal
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
