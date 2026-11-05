<div>
    @if($isOpen)
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-on-surface/35 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-labelledby="ppdb-doc-replace-title">
            <button type="button" wire:click="cancel" class="absolute inset-0 cursor-default" aria-label="Tutup"></button>
            <div class="relative w-full max-w-lg rounded-2xl border border-outline-variant bg-surface shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-outline-variant bg-surface-container-lowest px-5 py-4">
                    <div>
                        <h2 id="ppdb-doc-replace-title" class="text-headline-sm font-headline-sm text-on-surface">Ganti / Unggah Dokumen</h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Jenis: {{ $documentTypeLabel }}</p>
                    </div>
                    <button type="button" wire:click="cancel" class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-error" aria-label="Batal">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form wire:submit="save" class="px-5 py-4">
                    <div class="space-y-3">
                        @if($currentOriginalName)
                            <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3">
                                <p class="text-body-sm text-on-surface-variant">Berkas saat ini</p>
                                <p class="mt-1 break-all font-semibold text-on-surface">{{ $currentOriginalName }}</p>
                            </div>
                        @else
                            <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3">
                                <p class="text-body-sm text-on-surface-variant">Belum ada berkas</p>
                                <p class="mt-1 font-semibold text-on-surface">Unggah dokumen untuk tipe ini</p>
                            </div>
                        @endif
                        <div>
                            <label class="mb-1 block text-label-md font-label-md text-on-surface" for="ppdb-doc-file">Pilih berkas (PDF/JPG/JPEG/PNG, maks 2MB)</label>
                            <input id="ppdb-doc-file" type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-body-md text-on-surface file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-label-md file:text-on-primary hover:file:bg-primary/90">
                            <div wire:loading wire:target="file" class="mt-1 text-body-sm text-on-surface-variant">Mengunggah...</div>
                            <x-input-error :messages="$errors->get('file')" class="mt-1" />
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="cancel" class="rounded-lg border border-outline-variant px-4 py-2 text-label-md text-on-surface-variant hover:bg-surface-container">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-primary px-4 py-2 text-label-md text-on-primary hover:bg-primary/90 disabled:opacity-60">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
