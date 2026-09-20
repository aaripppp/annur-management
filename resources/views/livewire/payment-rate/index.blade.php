<div>
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Tarif Pembayaran</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola tarif berdasarkan jenis pembayaran dan tingkat kelas.</p>
        </div>
        <button wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Tarif
        </button>
    </div>

    <!-- Summary Cards -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">request_quote</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Tarif</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 tracking-wider">{{ number_format($totalRates, 0, ',', '.') }} Tarif</p>
            </div>
        </div>
    </section>

    <!-- Toast Success -->
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
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Table -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant flex flex-col lg:flex-row justify-between items-center gap-4">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Tarif Pembayaran</h2>
            <div class="flex items-center gap-3 flex-wrap">
                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">filter_alt</span>
                <select wire:model.live="filterSchoolLevel" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[160px]">
                    <option value="">Semua Jenjang</option>
                    @foreach($schoolLevelOptions as $schoolLevel)
                        <option value="{{ $schoolLevel->value }}">{{ $schoolLevel->value }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterPaymentTypeId" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[160px]">
                    <option value="">Semua Jenis</option>
                    @foreach($paymentTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterClassLevel" class="border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2 px-3 min-w-[160px]">
                    <option value="">Semua Kelas</option>
                    @foreach($classLevelOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-3 px-3 w-14 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Jenis Pembayaran</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Tingkat Kelas</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Nominal</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Periode</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Berlaku Mulai</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap">Berlaku Sampai</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider whitespace-nowrap text-right">Aksi</th>
                    </tr>
                </thead>
                @php
                    // Base class yang sama untuk semua badge (presentasi murni) agar
                    // ukuran, tinggi, dan alignment konsisten; satu baris, tidak wrap.
                    $badgeBase = 'inline-flex items-center justify-center py-1 px-3 rounded-full text-label-sm font-label-sm whitespace-nowrap';
                    $levelBadgeClass = $badgeBase.' min-w-12 bg-primary-fixed text-on-primary-fixed';
                @endphp
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($paymentRates as $rate)
                        <tr wire:key="payment-rate-{{ $rate->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ ($paymentRates->currentPage() - 1) * $paymentRates->perPage() + $loop->iteration }}</td>
                            <td class="py-4 px-6 text-body-md text-on-surface font-medium whitespace-nowrap">{{ $rate->paymentType->name ?? '—' }}</td>
                            <td class="py-4 px-6 text-body-md text-on-surface align-middle whitespace-nowrap">
                                <span class="{{ $levelBadgeClass }}">{{ $rate->level_name }}</span>
                            </td>
                            <td class="py-4 px-6 text-body-md font-body-md text-on-surface whitespace-nowrap">
                                Rp {{ number_format($rate->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-4 px-6 align-middle whitespace-nowrap">
                                @php
                                    $freqLabel = $rate->billing_frequency ? $this->frequencyLabel($rate->billing_frequency) : ($rate->is_monthly ? 'Bulanan' : 'Sekali Bayar');

                                    $freqBadgeClass = match ($freqLabel) {
                                        'Bulanan' => 'bg-secondary-fixed text-secondary',
                                        'Tahunan' => 'bg-tertiary-fixed text-tertiary',
                                        default => 'bg-surface-container-high text-on-surface-variant',
                                    };
                                @endphp
                                <span class="{{ $badgeBase }} {{ $freqBadgeClass }}">{{ $freqLabel }}</span>
                            </td>
                            <td class="py-4 px-6 text-body-md text-on-surface-variant whitespace-nowrap">
                                {{ $rate->effective_from->format('d M Y') }}
                            </td>
                            <td class="py-4 px-6 text-body-md text-on-surface-variant whitespace-nowrap">
                                {{ $rate->effective_until ? $rate->effective_until->format('d M Y') : '—' }}
                            </td>
                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $rate->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button wire:click="confirmDelete({{ $rate->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">request_quote</span>
                                <p>Tidak ada tarif pembayaran.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-outline-variant">
            {{ $paymentRates->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    <!-- Modal Form -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">
                        {{ $isEditing ? 'Edit Tarif Pembayaran' : 'Tambah Tarif Pembayaran Baru' }}
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="save" class="flex flex-col gap-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="payment_type_id" class="block text-label-md font-label-md text-on-surface mb-1">Jenis Pembayaran <span class="text-error">*</span></label>
                                <select id="payment_type_id" wire:model="payment_type_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Pilih Jenis --</option>
                                    @foreach($paymentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                                @error('payment_type_id') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                @if(!$isEditing)
                                    <label class="block text-label-md font-label-md text-on-surface mb-1">Cakupan Tarif <span class="text-error">*</span></label>
                                    <div class="flex items-center gap-4 h-[42px]">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="target_scope" value="class" wire:model.live="target_scope" class="text-primary focus:ring-primary border-outline-variant">
                                            <span class="text-body-md text-on-surface">Per Kelas</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="radio" name="target_scope" value="school_level" wire:model.live="target_scope" class="text-primary focus:ring-primary border-outline-variant">
                                            <span class="text-body-md text-on-surface">Per Jenjang</span>
                                        </label>
                                    </div>
                                @else
                                    <p class="text-label-md font-label-md text-on-surface mb-1">Cakupan Tarif</p>
                                    <p class="h-[42px] flex items-center text-body-md text-on-surface-variant">Per Kelas</p>
                                @endif
                            </div>
                        </div>
                        <div>
                            @if($target_scope === 'school_level' && !$isEditing)
                                <label for="school_level" class="block text-label-md font-label-md text-on-surface mb-1">Jenjang <span class="text-error">*</span></label>
                                <select id="school_level" wire:model="school_level" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Pilih Jenjang --</option>
                                    @foreach($schoolLevelOptions as $schoolLevel)
                                        <option value="{{ $schoolLevel->value }}">{{ $schoolLevel->value }}</option>
                                    @endforeach
                                </select>
                                <p class="text-body-sm text-on-surface-variant mt-1">Tarif akan diterapkan ke seluruh tingkat kelas pada jenjang ini.</p>
                                @error('school_level') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            @else
                                <label for="class_level" class="block text-label-md font-label-md text-on-surface mb-1">Tingkat Kelas <span class="text-error">*</span></label>
                                <select id="class_level" wire:model="class_level" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach(\App\Models\SchoolClass::levelLabels() as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('class_level') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            @endif
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="amount" class="block text-label-md font-label-md text-on-surface mb-1">Nominal (Rp) <span class="text-error">*</span></label>
                                <input type="text"
                                       id="amount"
                                       name="amount"
                                       value="{{ $amount }}"
                                       wire:model.blur="amount"
                                       x-data
                                       x-on:input="
                                           const el = $el;
                                           const caretPosition = el.selectionStart ?? el.value.length;
                                           const digitCountBeforeCaret = el.value.slice(0, caretPosition).replace(/\D/g, '').length;
                                           const digits = el.value.replace(/\D/g, '');
                                           let grouped = '';
                                           for (let i = 0; i < digits.length; i++) {
                                               grouped = digits[digits.length - 1 - i] + grouped;
                                               if ((i + 1) % 3 === 0 && i + 1 < digits.length) { grouped = '.' + grouped; }
                                           }
                                           el.value = grouped;
                                           let seenDigits = 0;
                                           let caretIndex = 0;
                                           while (caretIndex < el.value.length && seenDigits < digitCountBeforeCaret) {
                                               if (/\d/.test(el.value[caretIndex])) { seenDigits++; }
                                               caretIndex++;
                                           }
                                           el.setSelectionRange(caretIndex, caretIndex);
                                       "
                                       inputmode="numeric"
                                       autocomplete="off"
                                       class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"
                                       placeholder="Contoh: 990.000">
                                @error('amount') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-label-md font-label-md text-on-surface mb-1">Periode Tagihan <span class="text-error">*</span></label>
                                <div class="flex items-center gap-4 h-[42px]">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="billing_frequency" value="monthly" wire:model="billing_frequency" class="text-primary focus:ring-primary border-outline-variant">
                                        <span class="text-body-md text-on-surface">Bulanan</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="billing_frequency" value="yearly" wire:model="billing_frequency" class="text-primary focus:ring-primary border-outline-variant">
                                        <span class="text-body-md text-on-surface">Tahunan</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="billing_frequency" value="one_time" wire:model="billing_frequency" class="text-primary focus:ring-primary border-outline-variant">
                                        <span class="text-body-md text-on-surface">Sekali Bayar</span>
                                    </label>
                                </div>
                                @error('billing_frequency') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="effective_from" class="block text-label-md font-label-md text-on-surface mb-1">Berlaku Mulai <span class="text-error">*</span></label>
                                <input type="date" id="effective_from" wire:model="effective_from" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('effective_from') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="effective_until" class="block text-label-md font-label-md text-on-surface mb-1">Berlaku Sampai <span class="text-on-surface-variant">(Opsional)</span></label>
                                <input type="date" id="effective_until" wire:model="effective_until" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('effective_until') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </form>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">Batal</button>
                    <button type="button" wire:click="save" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete_forever</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Tarif?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Tindakan ini tidak bisa dibatalkan.<br>Data tarif akan dihapus secara permanen.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDelete" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button wire:click="delete" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
