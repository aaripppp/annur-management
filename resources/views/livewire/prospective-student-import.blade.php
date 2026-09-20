<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <a href="{{ route('calon-siswa.index') }}" wire:navigate class="inline-flex items-center gap-1 text-label-md text-primary hover:underline mb-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Data Calon Siswa
            </a>
            <h1 class="text-display-sm font-display-sm text-on-surface">Import Calon Siswa</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Validasi seluruh data sebelum calon siswa dan tagihan pendaftaran dibuat.</p>
        </div>
        <div class="flex items-center gap-2 text-label-md text-on-surface-variant">
            @foreach([1 => 'Setup', 2 => 'Preview', 3 => 'Selesai'] as $number => $label)
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $step >= $number ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant' }}">{{ $number }}</span>
                <span class="hidden sm:inline {{ $step === $number ? 'text-on-surface font-semibold' : '' }}">{{ $label }}</span>
                @if(!$loop->last)<span class="w-5 h-px bg-outline-variant"></span>@endif
            @endforeach
        </div>
    </div>

    @if($step === 1)
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-6">
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant">
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Setup Import</h2>
                    <p class="text-body-sm text-on-surface-variant mt-1">Tahun ajaran tujuan berlaku untuk seluruh baris file.</p>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label for="import-academic-year" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran Tujuan <span class="text-error">*</span></label>
                        <select id="import-academic-year" wire:model="academicYearId" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <option value="">-- Pilih Tahun Ajaran --</option>
                            @foreach($academicYears as $academicYear)
                                <option value="{{ $academicYear->id }}">{{ $academicYear->year }}{{ $academicYear->is_active ? ' (Aktif)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('academicYearId') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="prospective-import-file" class="block text-label-md font-label-md text-on-surface mb-1">File Excel <span class="text-error">*</span></label>
                        <input id="prospective-import-file" type="file" wire:model="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full text-body-sm text-on-surface-variant file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-primary-fixed file:text-primary file:font-label-md hover:file:bg-primary-fixed/80">
                        <p class="text-body-sm text-on-surface-variant mt-1">Format .xlsx, maksimum 12 MB. Baris kosong akan diabaikan.</p>
                        @error('file') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end">
                    <button type="button" wire:click="previewImport" wire:loading.attr="disabled" class="bg-primary hover:bg-primary/90 disabled:opacity-60 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">
                        <span wire:loading.remove wire:target="previewImport">Validasi & Preview</span>
                        <span wire:loading wire:target="previewImport">Memvalidasi...</span>
                    </button>
                </div>
            </section>

            <aside class="bg-surface-container-low border border-outline-variant rounded-2xl p-5 h-fit">
                <div class="w-10 h-10 rounded-xl bg-secondary-container text-on-secondary-container flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <h2 class="text-title-lg font-title-lg text-on-surface">Template Excel</h2>
                <p class="text-body-sm text-on-surface-variant mt-2">Gunakan nama kelas persis seperti master data. Sheet referensi kelas sudah disertakan.</p>
                <button type="button" wire:click="downloadTemplate" class="mt-4 w-full border border-primary text-primary hover:bg-primary-fixed px-4 py-2.5 rounded-xl font-label-lg transition-colors inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Download Template
                </button>
                <div class="mt-4 pt-4 border-t border-outline-variant text-body-sm text-on-surface-variant space-y-1">
                    <p class="text-on-surface font-label-md font-label-md">WAJIB</p>
                    <p>Nama Lengkap</p>
                    <p>Kelas Tujuan</p>
                    <p class="pt-2 text-on-surface font-label-md font-label-md">OPSIONAL</p>
                    <p>Nama Panggilan &bull; Jenis Kelamin (L/P)</p>
                    <p>Nama Orang Tua &bull; No Telp Orang Tua</p>
                    <p>Alamat &bull; Catatan</p>
                    <p class="pt-2">File minimal cukup berisi kolom Nama Lengkap + Kelas Tujuan.</p>
                    <p>Tahun ajaran tujuan ditentukan dari halaman ini.</p>
                </div>
            </aside>
        </div>
    @elseif($step === 2)
        <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Import Calon Siswa - Preview</h2>
                    <p class="text-body-sm text-on-surface-variant mt-1">{{ $preview['academic_year'] }}</p>
                </div>
                @if($preview['has_errors'])
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-error-container text-on-error-container text-label-md font-label-md">
                        <span class="material-symbols-outlined text-[18px]">error</span>
                        Perbaiki file sebelum import
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-secondary-container text-on-secondary-container text-label-md font-label-md">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        Semua data valid
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 px-6 py-4">
                @foreach([
                    ['value' => $preview['summary']['total'], 'label' => 'Data ditemukan', 'icon' => 'table_rows', 'icon_class' => 'bg-surface-container-high text-on-surface-variant'],
                    ['value' => $preview['summary']['new'], 'label' => 'Calon Siswa Baru', 'icon' => 'person_add', 'icon_class' => 'bg-primary-fixed text-on-primary-fixed'],
                    ['value' => $preview['summary']['errors'], 'label' => 'Bermasalah', 'icon' => 'error', 'icon_class' => 'bg-error-container text-on-error-container'],
                ] as $card)
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl px-3.5 py-3 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $card['icon_class'] }}">
                            <span class="material-symbols-outlined text-[18px]">{{ $card['icon'] }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-title-lg font-title-lg text-on-surface font-numeric-data leading-none">{{ $card['value'] }}</p>
                            <p class="text-body-sm text-on-surface-variant mt-1 truncate">{{ $card['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mx-6 mb-6 border border-outline-variant rounded-xl overflow-auto max-h-[55vh]">
                <table class="w-full min-w-[800px] text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-surface-container-low">
                        <tr class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">
                            <th class="px-4 py-3">Baris</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Kelas Tujuan</th>
                            <th class="px-4 py-3 min-w-[280px]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @foreach($preview['rows'] as $row)
                            <tr class="{{ $row['status'] === 'error' ? 'bg-error-container/10' : '' }}">
                                <td class="px-4 py-3 text-body-sm text-on-surface-variant" data-preview-position="{{ $loop->iteration }}">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 text-body-md text-on-surface">{{ $row['nama_lengkap'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-body-md text-on-surface whitespace-nowrap">{{ $row['kelas'] ?: '-' }}</td>
                                <td class="px-4 py-3 align-middle min-w-[280px]">
                                    <div class="flex flex-col items-start justify-center gap-1">
                                        <div class="inline-flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-label-sm font-label-sm leading-5 {{ match($row['status']) {
                                             'new' => 'bg-primary-container text-on-primary-container',
                                             default => 'bg-error-container text-on-error-container',
                                            } }}">
                                                {{ $row['status_label'] }}
                                            </span>
                                            @if($row['warnings'] !== [] && $row['status'] === 'new')
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-label-sm font-label-sm leading-5 bg-surface-container-high text-on-surface-variant" title="{{ implode(' ', $row['warnings']) }}">
                                                    Perlu dicek admin
                                                </span>
                                            @endif
                                        </div>
                                        @if($row['status'] === 'error')
                                            <p class="text-body-sm leading-snug text-error">
                                                {{ implode(' ', $row['errors']) }}
                                            </p>
                                        @endif
                                        @if($row['warnings'] !== [] && $row['status'] !== 'error')
                                            <p class="text-body-sm leading-snug text-on-surface-variant">
                                                {{ implode(' ', $row['warnings']) }}
                                            </p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('import')
                <div class="mx-6 mb-4 rounded-xl bg-error-container text-on-error-container px-4 py-3 text-body-sm">{{ $message }}</div>
            @enderror

            <div class="px-6 py-4 border-t border-outline-variant bg-surface flex flex-col-reverse sm:flex-row justify-end gap-3 sticky bottom-0">
                <button type="button" wire:click="backToSetup" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container rounded-xl transition-colors">Ganti File</button>
                <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" @disabled($preview['has_errors']) class="bg-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">
                    <span wire:loading.remove wire:target="confirmImport">Konfirmasi & Import</span>
                    <span wire:loading wire:target="confirmImport">Mengimpor...</span>
                </button>
            </div>
        </section>
    @else
        <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-8 text-center max-w-2xl mx-auto">
            <div class="w-16 h-16 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center mx-auto">
                <span class="material-symbols-outlined text-[34px]">check_circle</span>
            </div>
            <h2 class="text-headline-md font-headline-md text-on-surface mt-4">Import Berhasil</h2>
            <p class="text-title-lg text-on-surface mt-2">{{ $result['total'] }} calon siswa diproses</p>
            <p class="text-body-md text-on-surface-variant mt-1">{{ $result['academic_year'] }}</p>

            <div class="grid grid-cols-1 gap-3 mt-6 text-left">
                <div class="bg-primary-container rounded-xl p-4">
                    <p class="text-headline-sm font-headline-sm text-on-primary-container">{{ $result['new'] }}</p>
                    <p class="text-body-sm text-on-primary-container">Calon Siswa Baru</p>
                </div>
            </div>

            @if($result['breakdown'] !== [])
                <div class="mt-5 border border-outline-variant rounded-xl divide-y divide-outline-variant text-left">
                    @foreach($result['breakdown'] as $jenjang => $count)
                        <div class="flex justify-between px-4 py-2.5 text-body-md">
                            <span class="text-on-surface-variant">{{ $jenjang }}</span>
                            <span class="font-label-md text-on-surface">{{ $count }} calon siswa</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('calon-siswa.index') }}" wire:navigate class="mt-6 inline-flex items-center justify-center bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors">
                Kembali ke Data Calon Siswa
            </a>
        </section>
    @endif
</div>
