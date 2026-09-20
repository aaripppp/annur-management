@if($isConvertModalOpen)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="convert-title">
        <div class="absolute inset-0 bg-black/40" wire:click="closeConvertModal"></div>
        <div class="relative bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" wire:key="convert-modal-{{ $prospectiveStudent->id }}">
            <div class="px-6 py-5 border-b border-outline-variant flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-primary text-[22px]">person_add</span>
                </div>
                <div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface" id="convert-title">Jadikan Siswa?</h3>
                    <p class="text-body-sm text-on-surface-variant mt-1">Calon siswa ini akan dibuat sebagai siswa resmi untuk tahun ajaran tujuan dengan buku tagihan awal otomatis.</p>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface-container-low/40 space-y-3 border-b border-outline-variant/60">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-body-sm text-on-surface-variant">Nama</span>
                    <span class="text-body-sm font-semibold text-on-surface text-right">{{ $prospectiveStudent->nama_lengkap }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-body-sm text-on-surface-variant">No. Pendaftaran</span>
                    <span class="text-body-sm font-semibold text-on-surface font-numeric-data text-right">{{ $prospectiveStudent->registration_number }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-body-sm text-on-surface-variant">Tahun Ajaran Tujuan</span>
                    <span class="text-body-sm font-semibold text-on-surface font-numeric-data text-right">{{ $prospectiveStudent->academicYear?->year ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-body-sm text-on-surface-variant">Kelas Tujuan</span>
                    <span class="text-body-sm font-semibold text-on-surface text-right">{{ $prospectiveStudent->schoolClass?->name ?? '-' }}</span>
                </div>
            </div>

            <div class="px-6 py-4">
                <label for="convert-nis" class="block text-label-md font-label-md text-on-surface-variant mb-1.5">NIS <span class="text-on-surface-variant/60">(opsional)</span></label>
                <input
                    id="convert-nis"
                    type="text"
                    wire:model="convertNis"
                    maxlength="50"
                    placeholder="Nomor Induk Siswa"
                    class="w-full px-3.5 py-2.5 text-body-md text-on-surface bg-surface-container-lowest border border-outline-variant rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary placeholder:text-on-surface-variant/50"
                >
                @error('convertNis')
                    <p class="text-body-sm text-error mt-1.5">{{ $message }}</p>
                @enderror
                <p class="text-body-sm text-on-surface-variant mt-2">Tagihan pendaftaran dan pembayaran calon siswa tetap dipertahankan pada data calon siswa.</p>
            </div>

            <div class="px-6 py-4 bg-surface-container-low/60 border-t border-outline-variant flex justify-end gap-3">
                <button type="button" wire:click="closeConvertModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                <button type="button" wire:click="convertToStudent" class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-on-primary font-label-lg rounded-xl transition-colors shadow-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Jadikan Siswa
                </button>
            </div>
        </div>
    </div>
@endif