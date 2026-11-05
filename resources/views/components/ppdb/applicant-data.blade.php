@props(['applicant' => null, 'student' => null])

@php
    $record = $applicant ?? $student;
    $parents = $applicant?->ppdbParents ?? collect();
    $father = $parents->firstWhere('role', 'father');
    $mother = $parents->firstWhere('role', 'mother');
    $guardian = $parents->firstWhere('role', 'guardian');
    $siblings = $applicant?->ppdbSiblings ?? collect();
    $profile = $applicant?->ppdbProfile;
    $fatherName = $father?->nama ?? $student?->nama_ayah;
    $fatherPhone = $father?->no_telp ?? $student?->no_telp_ayah;
    $motherName = $mother?->nama ?? $student?->nama_ibu;
    $motherPhone = $mother?->no_telp ?? $student?->no_telp_ibu;
    $birthplace = $profile?->tempat_lahir ?? $student?->tempat_lahir;
    $birthdate = $profile?->tanggal_lahir ?? $student?->tanggal_lahir;

    $hasGuardian = $guardian && collect([
        $guardian->nama,
        $guardian->nik,
        $guardian->tempat_lahir,
        $guardian->tanggal_lahir,
        $guardian->pendidikan_terakhir,
        $guardian->email,
        $guardian->no_telp,
        $guardian->occupation,
        $guardian->income_range,
    ])->contains(fn ($value) => filled($value));

    $formatValue = fn ($value) => filled($value) ? $value : '-';
    $formatDate = function ($value) {
        if (! filled($value)) {
            return '-';
        }

        return $value instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::instance($value)->locale('id')->translatedFormat('d F Y')
            : (string) $value;
    };
    $formatNumber = function ($value) {
        if (! filled($value)) {
            return '-';
        }
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        }
        return (string) $value;
    };
    $formatBerkebutuhan = function ($value) {
        if (is_null($value)) {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['1', 'true', 'ya'], true)) {
                return 'Ya';
            }
            if (in_array($lower, ['0', 'false', 'tidak'], true)) {
                return 'Tidak';
            }
            return $value;
        }
        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 'Ya' : 'Tidak';
        }
        return '-';
    };
@endphp

<section class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest" x-data="ppdbBiodataAccordions()" data-ppdb-biodata>
    @php
        $ppdbProspectiveStudent = $applicant ?? ($student?->prospectiveStudent);
        $ppdbStudentId = $student && $applicant ? $student->id : null;
    @endphp
    <div class="flex flex-col gap-3 border-b border-outline-variant px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div>
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Biodata</h2>
            <p class="mt-1 text-body-sm text-on-surface-variant">{{ $student && $applicant ? 'Data pendaftaran PPDB yang tersimpan dan data keluarga.' : 'Data siswa dan keluarga yang tersedia pada sistem.' }}</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 sm:ml-auto" aria-label="Kontrol bagian biodata">
            @if($ppdbProspectiveStudent)
                @can('updateBiodata', $ppdbProspectiveStudent)
                    <button type="button" wire:click="$dispatch('ppdb-editor:open', { prospectId: {{ $ppdbProspectiveStudent->id }}, studentId: {{ $ppdbStudentId ?? 'null' }} })" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-primary px-3 py-2 text-label-md font-label-md text-primary transition-colors hover:bg-primary-fixed/50 sm:flex-none" data-ppdb-edit-biodata>
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span>
                        Edit Biodata PPDB
                    </button>
                @endcan
            @endif
            <button type="button" @click="openAllSections()" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-primary/40 px-3 py-2 text-label-md font-label-md text-primary transition-colors hover:bg-primary-fixed/50 sm:flex-none" data-accordion-open-all>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">unfold_more</span>
                Buka Semua
            </button>
            <button type="button" @click="closeAllSections()" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-label-md font-label-md text-on-surface-variant transition-colors hover:bg-surface-container sm:flex-none" data-accordion-close-all>
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">unfold_less</span>
                Tutup Semua
            </button>
        </div>
    </div>

    <div class="divide-y divide-outline-variant">
        <div>
            <button type="button" @click="toggleSection('student')" :aria-expanded="sections.student" aria-controls="ppdb-accordion-student" class="flex w-full items-center justify-between gap-4 px-4 py-3.5 text-left hover:bg-surface-container-low/60 sm:px-5" data-accordion="student">
                <span class="flex items-center gap-3 font-semibold text-on-surface"><span class="material-symbols-outlined text-primary" aria-hidden="true">person</span>Data Siswa</span>
                <span class="material-symbols-outlined text-on-surface-variant transition-transform" :class="sections.student && 'rotate-180'" aria-hidden="true">expand_more</span>
            </button>
            <div id="ppdb-accordion-student" x-show="sections.student" x-transition x-cloak class="border-t border-outline-variant/60 px-4 py-4 sm:px-5">
                @if($applicant)
                    <p class="mb-4 text-label-md font-semibold text-primary">Data Pendaftaran PPDB</p>
                    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Nomor Pendaftaran</p><p class="mt-1 break-words font-semibold text-on-surface font-numeric-data">{{ $applicant->registration_number }}</p></div>
                        <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Tahun Ajaran</p><p class="mt-1 font-semibold text-on-surface">{{ $applicant->academicYear?->year ?? '-' }}</p></div>
                        <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Jenjang / Kelas Tujuan</p><p class="mt-1 font-semibold text-on-surface">{{ $applicant->target_level_label ?? '-' }} / {{ $applicant->schoolClass?->name ?? '-' }}</p></div>
                        <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Sumber Pendaftaran</p><p class="mt-1 font-semibold text-on-surface">PPDB Online</p></div>
                        <div class="rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Kategori Pendaftar</p><p class="mt-1 font-semibold text-on-surface">{{ $applicant->applicant_category?->label() ?? '-' }}</p></div>
                    </div>
                @endif

                <p class="mb-3 text-label-md font-semibold text-on-surface">A. Identitas</p>
                <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Nama Lengkap</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $record?->nama_lengkap ?? '-' }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Nama Panggilan</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $record?->nama_panggilan ?? '-' }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Jenis Kelamin</p><p class="mt-1 font-semibold text-on-surface">{{ $record?->jenis_kelamin === 'L' ? 'Laki-laki' : ($record?->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">NIK Siswa</p><p class="mt-1 break-words font-semibold text-on-surface font-numeric-data">{{ $formatValue($profile?->nik_siswa) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Nomor KK</p><p class="mt-1 break-words font-semibold text-on-surface font-numeric-data">{{ $formatValue($profile?->nomor_kk) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Nomor Akta Kelahiran</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->nomor_akta_kelahiran) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Tempat Lahir</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $birthplace ?? '-' }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Tanggal Lahir</p><p class="mt-1 font-semibold text-on-surface">{{ $birthdate?->locale('id')->translatedFormat('d F Y') ?? '-' }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Asal Sekolah</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->asal_sekolah) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Anak ke-</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($profile?->anak_ke) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Jumlah Saudara</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($profile?->jumlah_saudara) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Status Anak</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->status_anak) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Agama</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->agama) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Bahasa Sehari-hari</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->bahasa_sehari_hari) }}</p></div>
                </div>

                <p class="mb-3 text-label-md font-semibold text-on-surface">B. Alamat</p>
                <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3 sm:col-span-2 xl:col-span-3"><p class="text-body-sm text-on-surface-variant">Alamat Lengkap</p><p class="mt-1 whitespace-pre-line break-words font-semibold text-on-surface">{{ $record?->alamat ?? '-' }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">RT</p><p class="mt-1 font-semibold text-on-surface">{{ $formatValue($profile?->rt) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">RW</p><p class="mt-1 font-semibold text-on-surface">{{ $formatValue($profile?->rw) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Kelurahan / Desa</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->kelurahan) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Kecamatan</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->kecamatan) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Kabupaten / Kota</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->kabupaten_kota) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Provinsi</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->provinsi) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Kode Pos</p><p class="mt-1 font-semibold text-on-surface">{{ $formatValue($profile?->kode_pos) }}</p></div>
                </div>

                <p class="mb-3 text-label-md font-semibold text-on-surface">C. Keadaan Jasmani</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Tinggi Badan (cm)</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($profile?->tinggi_badan) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Berat Badan (kg)</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($profile?->berat_badan) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Lingkar Kepala (cm)</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($profile?->lingkar_kepala) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Golongan Darah</p><p class="mt-1 font-semibold text-on-surface">{{ $formatValue($profile?->golongan_darah) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3 sm:col-span-2"><p class="text-body-sm text-on-surface-variant">Riwayat Penyakit</p><p class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($profile?->riwayat_penyakit) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Berkebutuhan Khusus</p><p class="mt-1 font-semibold text-on-surface" data-ppdb-special-needs="{{ $formatBerkebutuhan($profile?->berkebutuhan_khusus) }}">{{ $formatBerkebutuhan($profile?->berkebutuhan_khusus) }}</p></div>
                    <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3 sm:col-span-2 xl:col-span-3"><p class="text-body-sm text-on-surface-variant">Keterangan Kebutuhan Khusus</p><p class="mt-1 whitespace-pre-line break-words font-semibold text-on-surface">{{ $formatValue($profile?->keterangan_kebutuhan_khusus) }}</p></div>
                </div>
            </div>
        </div>

        @foreach([
            ['key' => 'father', 'title' => 'Data Ayah', 'icon' => 'man', 'record' => $father, 'name' => $fatherName, 'phone' => $fatherPhone],
            ['key' => 'mother', 'title' => 'Data Ibu', 'icon' => 'woman', 'record' => $mother, 'name' => $motherName, 'phone' => $motherPhone],
        ] as $parentSection)
            <div>
                <button type="button" @click="toggleSection('{{ $parentSection['key'] }}')" :aria-expanded="sections.{{ $parentSection['key'] }}" aria-controls="ppdb-accordion-{{ $parentSection['key'] }}" class="flex w-full items-center justify-between gap-4 px-4 py-3.5 text-left hover:bg-surface-container-low/60 sm:px-5" data-accordion="{{ $parentSection['key'] }}">
                    <span class="flex items-center gap-3 font-semibold text-on-surface"><span class="material-symbols-outlined text-primary" aria-hidden="true">{{ $parentSection['icon'] }}</span>{{ $parentSection['title'] }}</span>
                    <span class="material-symbols-outlined text-on-surface-variant transition-transform" :class="sections.{{ $parentSection['key'] }} && 'rotate-180'" aria-hidden="true">expand_more</span>
                </button>
                <div id="ppdb-accordion-{{ $parentSection['key'] }}" x-show="sections.{{ $parentSection['key'] }}" x-transition x-cloak class="border-t border-outline-variant/60 px-4 py-4 sm:px-5">
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Nama Lengkap</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $parentSection['name'] ?? '-' }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">NIK</dt><dd class="mt-1 break-words font-semibold text-on-surface font-numeric-data">{{ $formatValue($parentSection['record']?->nik) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Tempat Lahir</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($parentSection['record']?->tempat_lahir) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Tanggal Lahir</dt><dd class="mt-1 font-semibold text-on-surface">{{ $formatDate($parentSection['record']?->tanggal_lahir) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Pendidikan Terakhir</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($parentSection['record']?->pendidikan_terakhir) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Pekerjaan</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($parentSection['record']?->occupation) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Penghasilan</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $parentSection['record']?->income_range?->label() ?? '-' }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Nomor WhatsApp</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($parentSection['phone']) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Email</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($parentSection['record']?->email) }}</dd></div>
                    </dl>
                </div>
            </div>
        @endforeach

        <div>
            <button type="button" @click="toggleSection('guardian')" :aria-expanded="sections.guardian" aria-controls="ppdb-accordion-guardian" class="flex w-full items-center justify-between gap-4 px-4 py-3.5 text-left hover:bg-surface-container-low/60 sm:px-5" data-accordion="guardian">
                <span class="flex items-center gap-3 font-semibold text-on-surface"><span class="material-symbols-outlined text-primary" aria-hidden="true">supervisor_account</span>Data Wali</span>
                <span class="material-symbols-outlined text-on-surface-variant transition-transform" :class="sections.guardian && 'rotate-180'" aria-hidden="true">expand_more</span>
            </button>
            <div id="ppdb-accordion-guardian" x-show="sections.guardian" x-transition x-cloak class="border-t border-outline-variant/60 px-4 py-4 sm:px-5">
                @if($hasGuardian)
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Nama Lengkap</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($guardian->nama) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">NIK</dt><dd class="mt-1 break-words font-semibold text-on-surface font-numeric-data">{{ $formatValue($guardian->nik) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Tempat Lahir</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($guardian->tempat_lahir) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Tanggal Lahir</dt><dd class="mt-1 font-semibold text-on-surface">{{ $formatDate($guardian->tanggal_lahir) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Pendidikan Terakhir</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($guardian->pendidikan_terakhir) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Pekerjaan</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($guardian->occupation) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Penghasilan</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $guardian->income_range?->label() ?? '-' }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Nomor WhatsApp</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($guardian->no_telp) }}</dd></div>
                        <div class="rounded-lg bg-surface-container-low/40 px-3.5 py-3"><dt class="text-body-sm text-on-surface-variant">Email</dt><dd class="mt-1 break-words font-semibold text-on-surface">{{ $formatValue($guardian->email) }}</dd></div>
                    </dl>
                @else
                    <p class="text-body-md text-on-surface-variant">Tidak ada data wali.</p>
                @endif
            </div>
        </div>

        <div>
            <button type="button" @click="toggleSection('siblings')" :aria-expanded="sections.siblings" aria-controls="ppdb-accordion-siblings" class="flex w-full items-center justify-between gap-4 px-4 py-3.5 text-left hover:bg-surface-container-low/60 sm:px-5" data-accordion="siblings">
                <span class="flex items-center gap-3 font-semibold text-on-surface"><span class="material-symbols-outlined text-primary" aria-hidden="true">groups</span>Saudara Kandung</span>
                <span class="material-symbols-outlined text-on-surface-variant transition-transform" :class="sections.siblings && 'rotate-180'" aria-hidden="true">expand_more</span>
            </button>
            <div id="ppdb-accordion-siblings" x-show="sections.siblings" x-transition x-cloak class="border-t border-outline-variant/60 px-4 py-4 sm:px-5">
                @if($applicant && $applicant->ppdbProfile)
                    <div class="mb-3 rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Jumlah Saudara (data terpisah)</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($applicant->ppdbProfile->jumlah_saudara) }}</p></div>
                @elseif($profile)
                    <div class="mb-3 rounded-lg bg-surface-container-low/60 px-3.5 py-3"><p class="text-body-sm text-on-surface-variant">Jumlah Saudara (data terpisah)</p><p class="mt-1 font-semibold text-on-surface">{{ $formatNumber($profile->jumlah_saudara) }}</p></div>
                @endif
                @if($siblings->isEmpty())
                    <p class="text-body-md text-on-surface-variant">Tidak ada data saudara kandung.</p>
                @else
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        @foreach($siblings as $sibling)
                            <article class="min-w-0 rounded-xl border border-outline-variant p-4">
                                <p class="break-words font-semibold text-on-surface">{{ $sibling->nama_lengkap }}</p>
                                <div class="mt-3 grid grid-cols-1 gap-3 text-body-sm sm:grid-cols-2">
                                    <div><p class="text-on-surface-variant">Tanggal Lahir</p><p class="mt-0.5 text-on-surface">{{ $sibling->tanggal_lahir?->locale('id')->translatedFormat('d F Y') ?? '-' }}</p></div>
                                    <div><p class="text-on-surface-variant">Jenis Kelamin</p><p class="mt-0.5 text-on-surface">{{ $sibling->jenis_kelamin === 'L' ? 'Laki-laki' : ($sibling->jenis_kelamin === 'P' ? 'Perempuan' : '-') }}</p></div>
                                    <div class="sm:col-span-2"><p class="text-on-surface-variant">Pendidikan / Pekerjaan</p><p class="mt-0.5 break-words text-on-surface">{{ $sibling->pendidikan_pekerjaan ?? '-' }}</p></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
