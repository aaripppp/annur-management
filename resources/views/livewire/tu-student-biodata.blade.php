<div class="flex flex-col gap-stack-lg">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-label-md text-primary">Biodata Siswa · Unit {{ $effectiveSchoolLevel }}</p>
            <h1 class="text-display-sm font-display-sm text-on-surface">{{ $student->nama_lengkap }}</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Informasi identitas siswa.</p>
        </div>
        <a href="{{ route('siswa.index') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-4 py-2 rounded-xl font-label-lg transition-colors w-fit">
            Kembali ke Data Siswa
        </a>
    </div>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
            <div>
                <p class="text-label-sm text-on-surface-variant">NIS</p>
                <p class="text-body-lg text-on-surface">{{ $student->nis ?: '-' }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Nama Panggilan</p>
                <p class="text-body-lg text-on-surface">{{ $student->nama_panggilan ?: '-' }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Kelas Efektif</p>
                <p class="text-body-lg text-on-surface">{{ $effectiveClassName }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Status</p>
                <p class="text-body-lg text-on-surface">{{ $student->status_label }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Jenis Kelamin</p>
                <p class="text-body-lg text-on-surface">{{ match ($student->jenis_kelamin) { 'L' => 'Laki-laki', 'P' => 'Perempuan', default => '-' } }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Tempat, Tanggal Lahir</p>
                <p class="text-body-lg text-on-surface">
                    {{ $student->tempat_lahir ?: '-' }}{{ $student->tanggal_lahir ? ', '.$student->tanggal_lahir->translatedFormat('d F Y') : '' }}
                </p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Tanggal Masuk</p>
                <p class="text-body-lg text-on-surface">{{ $student->entry_date?->translatedFormat('d F Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Kategori</p>
                <p class="text-body-lg text-on-surface">{{ $student->categories->sortBy('name')->pluck('name')->join(', ') ?: 'Siswa Normal' }}</p>
            </div>
            <div class="md:col-span-2">
                <p class="text-label-sm text-on-surface-variant">Alamat</p>
                <p class="text-body-lg text-on-surface whitespace-pre-line">{{ $student->alamat ?: '-' }}</p>
            </div>
        </div>
    </section>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-lg">
        <h2 class="text-headline-sm font-headline-sm text-on-surface mb-5">Orang Tua</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
            <div>
                <p class="text-label-sm text-on-surface-variant">Nama Ayah</p>
                <p class="text-body-lg text-on-surface">{{ $student->nama_ayah ?: '-' }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Nomor Telepon Ayah</p>
                <p class="text-body-lg text-on-surface">{{ $student->no_telp_ayah ?: '-' }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Nama Ibu</p>
                <p class="text-body-lg text-on-surface">{{ $student->nama_ibu ?: '-' }}</p>
            </div>
            <div>
                <p class="text-label-sm text-on-surface-variant">Nomor Telepon Ibu</p>
                <p class="text-body-lg text-on-surface">{{ $student->no_telp_ibu ?: '-' }}</p>
            </div>
        </div>
    </section>
</div>
