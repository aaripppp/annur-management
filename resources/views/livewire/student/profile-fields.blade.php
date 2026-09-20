@php($fieldPrefix = $fieldPrefix ?? 'student-profile')

<div class="border-t border-outline-variant pt-5">
    <h4 class="text-title-md font-title-md text-on-surface">Biodata Tambahan</h4>
    <p class="text-body-sm text-on-surface-variant mt-1">Seluruh field pada bagian ini bersifat opsional.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="{{ $fieldPrefix }}-tempat-lahir" class="block text-label-md font-label-md text-on-surface mb-1">Tempat Lahir</label>
        <input type="text" id="{{ $fieldPrefix }}-tempat-lahir" wire:model="tempat_lahir" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('tempat_lahir') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="{{ $fieldPrefix }}-tanggal-lahir" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Lahir</label>
        <input type="date" id="{{ $fieldPrefix }}-tanggal-lahir" wire:model="tanggal_lahir" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('tanggal_lahir') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="{{ $fieldPrefix }}-nama-ayah" class="block text-label-md font-label-md text-on-surface mb-1">Nama Ayah</label>
        <input type="text" id="{{ $fieldPrefix }}-nama-ayah" wire:model="nama_ayah" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('nama_ayah') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="{{ $fieldPrefix }}-no-telp-ayah" class="block text-label-md font-label-md text-on-surface mb-1">No. Telepon Ayah</label>
        <input type="text" inputmode="tel" id="{{ $fieldPrefix }}-no-telp-ayah" wire:model="no_telp_ayah" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: 081234567890">
        @error('no_telp_ayah') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="{{ $fieldPrefix }}-nama-ibu" class="block text-label-md font-label-md text-on-surface mb-1">Nama Ibu</label>
        <input type="text" id="{{ $fieldPrefix }}-nama-ibu" wire:model="nama_ibu" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('nama_ibu') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="{{ $fieldPrefix }}-no-telp-ibu" class="block text-label-md font-label-md text-on-surface mb-1">No. Telepon Ibu</label>
        <input type="text" inputmode="tel" id="{{ $fieldPrefix }}-no-telp-ibu" wire:model="no_telp_ibu" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: +6281234567890">
        @error('no_telp_ibu') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>

<div>
    <label for="{{ $fieldPrefix }}-foto" class="block text-label-md font-label-md text-on-surface mb-1">Foto Siswa</label>
    <div class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-low p-4">
        @if($existing_foto && !$remove_foto)
            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existing_foto) }}" alt="Foto siswa" class="w-16 h-16 rounded-xl object-cover border border-outline-variant shrink-0">
        @else
            <div class="w-16 h-16 rounded-xl bg-surface-container-high border border-outline-variant text-on-surface-variant flex items-center justify-center shrink-0" aria-hidden="true">
                <span class="material-symbols-outlined text-[30px]">person</span>
            </div>
        @endif
        <div class="flex-1 min-w-0">
            <input type="file" id="{{ $fieldPrefix }}-foto" wire:model="foto_upload" accept="image/jpeg,image/png,image/webp" class="block w-full text-body-sm text-on-surface-variant file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-primary-fixed file:text-primary file:font-label-md">
            <p class="text-body-sm text-on-surface-variant mt-1">JPG, JPEG, PNG, atau WEBP. Maksimum 2 MB.</p>
            <p wire:loading wire:target="foto_upload" class="text-body-sm text-primary mt-1">Mengunggah foto...</p>
            @error('foto_upload') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
        </div>
    </div>
    @if($existing_foto)
        <label class="inline-flex items-center gap-2 mt-2 cursor-pointer text-body-sm text-on-surface-variant">
            <input type="checkbox" wire:model.live="remove_foto" class="rounded border-outline-variant text-error focus:ring-error">
            Hapus foto saat profil disimpan
        </label>
    @endif
</div>
