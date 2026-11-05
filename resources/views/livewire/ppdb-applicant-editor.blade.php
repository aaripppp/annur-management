<div>
    @if($successMessage)
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition class="fixed right-4 top-24 z-[70] flex max-w-sm items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800 shadow-lg" role="status" data-ppdb-editor-success>
            <span class="material-symbols-outlined text-green-600" aria-hidden="true">check_circle</span>
            <p class="text-body-sm font-medium">{{ $successMessage }}</p>
        </div>
    @endif

    @if($isOpen)
        @php
            $inputClass = 'w-full rounded-lg border-outline-variant bg-surface-container-lowest px-3 py-2 text-body-md text-on-surface shadow-sm focus:border-primary focus:ring-primary';
            $labelClass = 'mb-1 block text-label-md font-label-md text-on-surface';
            $sectionClass = 'rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:p-5';
        @endphp

        <div class="fixed inset-0 z-[65] flex items-center justify-center bg-on-surface/35 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-labelledby="ppdb-editor-title" data-ppdb-editor-modal>
            <button type="button" wire:click="cancel" class="absolute inset-0 cursor-default" aria-label="Tutup editor biodata"></button>
            <div class="relative flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-outline-variant bg-surface-container-lowest px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="ppdb-editor-title" class="text-headline-sm font-headline-sm text-on-surface">Edit Biodata PPDB</h2>
                        <p class="mt-1 text-body-sm text-on-surface-variant">Perbarui data siswa dan keluarga tanpa mengubah dokumen atau data pembayaran.</p>
                    </div>
                    <button type="button" wire:click="cancel" class="rounded-lg p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-error" aria-label="Batal dan tutup">
                        <span class="material-symbols-outlined" aria-hidden="true">close</span>
                    </button>
                </div>

                <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto bg-surface-container-low/40 p-4 sm:p-6">
                        <section class="{{ $sectionClass }}">
                            <h3 class="mb-4 text-title-md font-semibold text-on-surface">Kategori Pendaftar</h3>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach($applicantCategories as $category)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-outline-variant p-3 transition-colors hover:bg-primary-fixed/30">
                                        <input type="radio" wire:model="applicantCategory" value="{{ $category->value }}" class="mt-0.5 border-outline text-primary focus:ring-primary">
                                        <span><span class="block font-semibold text-on-surface">{{ $category->label() }}</span><span class="mt-0.5 block text-body-sm text-on-surface-variant">Kategori informasi administrasi PPDB.</span></span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('applicantCategory')" class="mt-2" />
                            @if($applicantCategory === '')
                                <p class="mt-2 text-body-sm text-on-surface-variant">Data historis ini belum memiliki kategori. Pilih kategori saat klasifikasi sudah diketahui.</p>
                            @endif
                        </section>

                        <section class="{{ $sectionClass }}">
                            <h3 class="mb-4 text-title-md font-semibold text-on-surface">Identitas Siswa</h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="sm:col-span-2"><label class="{{ $labelClass }}" for="ppdb-name">Nama Lengkap <span class="text-error">*</span></label><input id="ppdb-name" type="text" wire:model="student.nama_lengkap" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('student.nama_lengkap')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-nickname">Nama Panggilan</label><input id="ppdb-nickname" type="text" wire:model="student.nama_panggilan" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('student.nama_panggilan')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-gender">Jenis Kelamin</label><select id="ppdb-gender" wire:model="student.jenis_kelamin" class="{{ $inputClass }}"><option value="">Belum diisi</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select><x-input-error :messages="$errors->get('student.jenis_kelamin')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-birthplace">Tempat Lahir</label><input id="ppdb-birthplace" type="text" wire:model="profile.tempat_lahir" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.tempat_lahir')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-birthdate">Tanggal Lahir</label><input id="ppdb-birthdate" type="date" wire:model="profile.tanggal_lahir" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.tanggal_lahir')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-school-origin">Asal Sekolah</label><input id="ppdb-school-origin" type="text" maxlength="255" wire:model="profile.asal_sekolah" class="{{ $inputClass }}"><p class="mt-1 text-xs text-on-surface-variant">Kosongkan jika belum pernah sekolah.</p><x-input-error :messages="$errors->get('profile.asal_sekolah')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-nik">NIK Siswa</label><input id="ppdb-nik" type="text" inputmode="numeric" maxlength="16" wire:model="profile.nik_siswa" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.nik_siswa')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-kk">Nomor KK</label><input id="ppdb-kk" type="text" inputmode="numeric" maxlength="16" wire:model="profile.nomor_kk" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.nomor_kk')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-birth-certificate">Nomor Akta Kelahiran</label><input id="ppdb-birth-certificate" type="text" wire:model="profile.nomor_akta_kelahiran" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.nomor_akta_kelahiran')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-child-order">Anak ke-</label><input id="ppdb-child-order" type="number" min="1" wire:model="profile.anak_ke" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.anak_ke')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-sibling-count">Jumlah Saudara</label><input id="ppdb-sibling-count" type="number" min="0" wire:model="profile.jumlah_saudara" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.jumlah_saudara')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-child-status">Status Anak</label><input id="ppdb-child-status" type="text" wire:model="profile.status_anak" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.status_anak')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-religion">Agama</label><input id="ppdb-religion" type="text" wire:model="profile.agama" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.agama')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-language">Bahasa Sehari-hari</label><input id="ppdb-language" type="text" wire:model="profile.bahasa_sehari_hari" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.bahasa_sehari_hari')" class="mt-1" /></div>
                            </div>
                        </section>

                        <section class="{{ $sectionClass }}">
                            <h3 class="mb-4 text-title-md font-semibold text-on-surface">Alamat</h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="sm:col-span-2 lg:col-span-4"><label class="{{ $labelClass }}" for="ppdb-address">Alamat Lengkap</label><textarea id="ppdb-address" rows="3" wire:model="student.alamat" class="{{ $inputClass }}"></textarea><x-input-error :messages="$errors->get('student.alamat')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-rt">RT</label><input id="ppdb-rt" type="text" inputmode="numeric" maxlength="3" wire:model="profile.rt" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.rt')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-rw">RW</label><input id="ppdb-rw" type="text" inputmode="numeric" maxlength="3" wire:model="profile.rw" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.rw')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-postal-code">Kode Pos</label><input id="ppdb-postal-code" type="text" inputmode="numeric" maxlength="5" wire:model="profile.kode_pos" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.kode_pos')" class="mt-1" /></div>
                                @foreach(['kelurahan' => 'Kelurahan / Desa', 'kecamatan' => 'Kecamatan', 'kabupaten_kota' => 'Kabupaten / Kota', 'provinsi' => 'Provinsi'] as $field => $label)
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ str_replace('_', '-', $field) }}">{{ $label }}</label><input id="ppdb-{{ str_replace('_', '-', $field) }}" type="text" wire:model="profile.{{ $field }}" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.'.$field)" class="mt-1" /></div>
                                @endforeach
                            </div>
                        </section>

                        <section class="{{ $sectionClass }}">
                            <h3 class="mb-4 text-title-md font-semibold text-on-surface">Keadaan Jasmani</h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="{{ $labelClass }}" for="ppdb-height">Tinggi Badan (cm)</label><input id="ppdb-height" type="number" min="0" max="250" step="0.01" wire:model="profile.tinggi_badan" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.tinggi_badan')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-weight">Berat Badan (kg)</label><input id="ppdb-weight" type="number" min="0" max="200" step="0.01" wire:model="profile.berat_badan" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.berat_badan')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-head">Lingkar Kepala (cm)</label><input id="ppdb-head" type="number" min="0" max="100" step="0.01" wire:model="profile.lingkar_kepala" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.lingkar_kepala')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-blood">Golongan Darah</label><input id="ppdb-blood" type="text" maxlength="5" wire:model="profile.golongan_darah" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('profile.golongan_darah')" class="mt-1" /></div>
                                <div class="sm:col-span-2"><label class="{{ $labelClass }}" for="ppdb-medical-history">Riwayat Penyakit</label><textarea id="ppdb-medical-history" rows="3" wire:model="profile.riwayat_penyakit" class="{{ $inputClass }}"></textarea><x-input-error :messages="$errors->get('profile.riwayat_penyakit')" class="mt-1" /></div>
                                <div><label class="{{ $labelClass }}" for="ppdb-special-needs">Berkebutuhan Khusus</label><select id="ppdb-special-needs" wire:model="profile.berkebutuhan_khusus" class="{{ $inputClass }}"><option value="">Belum diisi</option><option value="0">Tidak</option><option value="1">Ya</option></select><x-input-error :messages="$errors->get('profile.berkebutuhan_khusus')" class="mt-1" /></div>
                                <div class="sm:col-span-2 lg:col-span-4"><label class="{{ $labelClass }}" for="ppdb-special-needs-note">Keterangan Kebutuhan Khusus</label><textarea id="ppdb-special-needs-note" rows="2" wire:model="profile.keterangan_kebutuhan_khusus" class="{{ $inputClass }}"></textarea><x-input-error :messages="$errors->get('profile.keterangan_kebutuhan_khusus')" class="mt-1" /></div>
                            </div>
                        </section>

                        @foreach(['father' => 'Data Ayah', 'mother' => 'Data Ibu', 'guardian' => 'Data Wali (Opsional)'] as $role => $title)
                            <section class="{{ $sectionClass }}" wire:key="ppdb-parent-{{ $role }}">
                                <div class="mb-4 flex items-center justify-between gap-3"><h3 class="text-title-md font-semibold text-on-surface">{{ $title }}</h3>@if($role === 'guardian')<span class="text-body-sm text-on-surface-variant">Kosongkan seluruh kolom untuk menghapus data wali.</span>@endif</div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-name">Nama Lengkap</label><input id="ppdb-{{ $role }}-name" type="text" wire:model="parents.{{ $role }}.nama" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.nama')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-nik">NIK</label><input id="ppdb-{{ $role }}-nik" type="text" inputmode="numeric" maxlength="16" wire:model="parents.{{ $role }}.nik" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.nik')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-phone">Nomor WhatsApp</label><input id="ppdb-{{ $role }}-phone" type="text" wire:model="parents.{{ $role }}.no_telp" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.no_telp')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-birthplace">Tempat Lahir</label><input id="ppdb-{{ $role }}-birthplace" type="text" wire:model="parents.{{ $role }}.tempat_lahir" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.tempat_lahir')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-birthdate">Tanggal Lahir</label><input id="ppdb-{{ $role }}-birthdate" type="date" wire:model="parents.{{ $role }}.tanggal_lahir" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.tanggal_lahir')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-education">Pendidikan Terakhir</label><input id="ppdb-{{ $role }}-education" type="text" wire:model="parents.{{ $role }}.pendidikan_terakhir" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.pendidikan_terakhir')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-email">Email</label><input id="ppdb-{{ $role }}-email" type="email" wire:model="parents.{{ $role }}.email" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.email')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-occupation">Pekerjaan</label><input id="ppdb-{{ $role }}-occupation" type="text" wire:model="parents.{{ $role }}.occupation" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('parents.'.$role.'.occupation')" class="mt-1" /></div>
                                    <div><label class="{{ $labelClass }}" for="ppdb-{{ $role }}-income">Penghasilan</label><select id="ppdb-{{ $role }}-income" wire:model="parents.{{ $role }}.income_range" class="{{ $inputClass }}"><option value="">Belum diisi</option>@foreach($incomeOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><x-input-error :messages="$errors->get('parents.'.$role.'.income_range')" class="mt-1" /></div>
                                </div>
                            </section>
                        @endforeach

                        <section class="{{ $sectionClass }}">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div><h3 class="text-title-md font-semibold text-on-surface">Saudara Kandung</h3><p class="mt-1 text-body-sm text-on-surface-variant">Urutan mengikuti susunan kartu di bawah dan tidak mengubah kolom jumlah saudara.</p></div>
                                <button type="button" wire:click="addSibling" class="inline-flex items-center gap-1 rounded-lg border border-primary/50 px-3 py-2 text-label-md font-semibold text-primary hover:bg-primary-fixed/40"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>Tambah Saudara</button>
                            </div>
                            <x-input-error :messages="$errors->get('siblings')" class="mb-3" />
                            <div class="space-y-3">
                                @forelse($siblings as $index => $sibling)
                                    <article class="rounded-xl border border-outline-variant bg-surface-container-low/50 p-4" wire:key="ppdb-sibling-{{ $sibling['key'] }}" data-sibling-key="{{ $sibling['key'] }}">
                                        <div class="mb-3 flex items-center justify-between gap-3"><h4 class="font-semibold text-on-surface">Saudara {{ $index + 1 }}</h4><button type="button" wire:click="removeSibling('{{ $sibling['key'] }}')" class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-label-md text-error hover:bg-error-container"><span class="material-symbols-outlined text-[17px]" aria-hidden="true">delete</span>Hapus</button></div>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <div class="sm:col-span-2"><label class="{{ $labelClass }}">Nama Lengkap <span class="text-error">*</span></label><input type="text" wire:model="siblings.{{ $index }}.nama_lengkap" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('siblings.'.$index.'.nama_lengkap')" class="mt-1" /></div>
                                            <div><label class="{{ $labelClass }}">Tanggal Lahir <span class="text-error">*</span></label><input type="date" wire:model="siblings.{{ $index }}.tanggal_lahir" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('siblings.'.$index.'.tanggal_lahir')" class="mt-1" /></div>
                                            <div><label class="{{ $labelClass }}">Jenis Kelamin <span class="text-error">*</span></label><select wire:model="siblings.{{ $index }}.jenis_kelamin" class="{{ $inputClass }}"><option value="">Pilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select><x-input-error :messages="$errors->get('siblings.'.$index.'.jenis_kelamin')" class="mt-1" /></div>
                                            <div class="sm:col-span-2 lg:col-span-4"><label class="{{ $labelClass }}">Pendidikan / Pekerjaan <span class="text-error">*</span></label><input type="text" wire:model="siblings.{{ $index }}.pendidikan_pekerjaan" class="{{ $inputClass }}"><x-input-error :messages="$errors->get('siblings.'.$index.'.pendidikan_pekerjaan')" class="mt-1" /></div>
                                        </div>
                                    </article>
                                @empty
                                    <div class="rounded-xl border border-dashed border-outline-variant px-4 py-6 text-center text-body-md text-on-surface-variant">Tidak ada data saudara kandung.</div>
                                @endforelse
                            </div>
                        </section>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-outline-variant bg-surface-container-lowest px-5 py-4 sm:px-6">
                        <button type="button" wire:click="cancel" class="rounded-xl border border-outline-variant px-5 py-2.5 font-label-lg text-on-surface-variant transition-colors hover:bg-surface-container">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-label-lg text-on-primary shadow-sm transition-colors hover:bg-primary/90 disabled:cursor-wait disabled:opacity-60">
                            <span wire:loading.remove wire:target="save" class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                            <span wire:loading wire:target="save" class="material-symbols-outlined animate-spin text-[18px]" aria-hidden="true">progress_activity</span>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
