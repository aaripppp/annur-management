@php($createMode = ($mode ?? 'edit') === 'create')
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="daycare-nama-lengkap" class="block text-label-md font-label-md text-on-surface mb-1">Nama Lengkap <span class="text-error">*</span></label>
        <input id="daycare-nama-lengkap" type="text" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required>
        @error('nama_lengkap') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="daycare-nama-panggilan" class="block text-label-md font-label-md text-on-surface mb-1">Nama Panggilan</label>
        <input id="daycare-nama-panggilan" type="text" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('nama_panggilan') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="daycare-tempat-lahir" class="block text-label-md font-label-md text-on-surface mb-1">Tempat Lahir</label>
        <input id="daycare-tempat-lahir" type="text" wire:model="tempat_lahir" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('tempat_lahir') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="daycare-tanggal-lahir" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Lahir</label>
        <input id="daycare-tanggal-lahir" type="date" wire:model="tanggal_lahir" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('tanggal_lahir') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="daycare-kelas" class="block text-label-md font-label-md text-on-surface mb-1">Kelas <span class="text-error">*</span></label>
        <select id="daycare-kelas" wire:model="kelas" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required>
            <option value="">-- Pilih Kelas --</option>
            @foreach(['A', 'B', 'C', 'D', 'E'] as $classOption)
                <option value="{{ $classOption }}">{{ $classOption }}</option>
            @endforeach
        </select>
        @error('kelas') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <span class="block text-label-md font-label-md text-on-surface mb-2">Jenis Kelamin</span>
        <div class="flex gap-5 py-2">
            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" wire:model="jenis_kelamin" value="L" class="text-primary focus:ring-primary border-outline-variant"><span>Laki-laki (L)</span></label>
            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" wire:model="jenis_kelamin" value="P" class="text-primary focus:ring-primary border-outline-variant"><span>Perempuan (P)</span></label>
        </div>
        @error('jenis_kelamin') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>
<div>
    <label for="daycare-alamat" class="block text-label-md font-label-md text-on-surface mb-1">Alamat</label>
    <textarea id="daycare-alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"></textarea>
    @error('alamat') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
</div>
<div class="pt-1"><h4 class="text-label-lg font-label-lg text-on-surface">Data Orang Tua</h4><p class="text-body-sm text-on-surface-variant mt-0.5">Seluruh data orang tua bersifat opsional.</p></div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label for="daycare-nama-ayah" class="block text-label-md font-label-md text-on-surface mb-1">Nama Ayah</label>
        <input id="daycare-nama-ayah" type="text" wire:model="nama_ayah" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('nama_ayah') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="daycare-telepon-ayah" class="block text-label-md font-label-md text-on-surface mb-1">No. Telepon Ayah</label>
        <input id="daycare-telepon-ayah" type="tel" wire:model="no_telp_ayah" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('no_telp_ayah') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="daycare-nama-ibu" class="block text-label-md font-label-md text-on-surface mb-1">Nama Ibu</label>
        <input id="daycare-nama-ibu" type="text" wire:model="nama_ibu" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('nama_ibu') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
    <div>
        <label for="daycare-telepon-ibu" class="block text-label-md font-label-md text-on-surface mb-1">No. Telepon Ibu</label>
        <input id="daycare-telepon-ibu" type="tel" wire:model="no_telp_ibu" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
        @error('no_telp_ibu') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
    </div>
</div>
<div>
    <span class="block text-label-md font-label-md text-on-surface mb-2">Status @unless($createMode)<span class="text-error">*</span>@endunless</span>
    <div class="flex gap-5">
        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" wire:model="is_active" value="1" class="text-primary focus:ring-primary border-outline-variant"><span>Aktif</span></label>
        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" wire:model="is_active" value="0" class="text-primary focus:ring-primary border-outline-variant"><span>Nonaktif</span></label>
    </div>
    @error('is_active') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
</div>