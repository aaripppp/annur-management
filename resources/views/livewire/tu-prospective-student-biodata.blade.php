<div class="flex flex-col gap-stack-lg">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-label-md text-primary">Biodata Calon Siswa · Unit {{ $prospectiveStudent->target_level_label ?? '-' }}</p>
            <h1 class="text-display-sm font-display-sm text-on-surface">{{ $prospectiveStudent->nama_lengkap }}</h1>
            <p class="text-body-md text-on-surface-variant mt-1">
                <span class="font-numeric-data">{{ $prospectiveStudent->registration_number }}</span>
                &middot; {{ $prospectiveStudent->status_label }}
                &middot; {{ $prospectiveStudent->academicYear?->year ?? '-' }}
                &middot; {{ $prospectiveStudent->schoolClass?->name ?? 'Belum Ditentukan' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('calon-siswa.biodata.pdf', $prospectiveStudent) }}" target="_blank" rel="noopener" class="border border-primary text-primary hover:bg-primary-fixed px-4 py-2 rounded-xl font-label-lg transition-colors w-fit">
                Unduh PDF Biodata
            </a>
            <a href="{{ route('calon-siswa.index') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-4 py-2 rounded-xl font-label-lg transition-colors w-fit">
                Kembali ke Calon Siswa
            </a>
        </div>
    </div>

    <x-ppdb.applicant-data :applicant="$prospectiveStudent" />

    <x-ppdb.document-section :applicant="$prospectiveStudent" />
    <livewire:ppdb-document-replacer :prospective-student-id="$prospectiveStudent->id" :key="'ppdb-doc-replacer-tu-'.$prospectiveStudent->id" />
</div>
