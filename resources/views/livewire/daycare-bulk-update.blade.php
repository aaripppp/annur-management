<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <a href="{{ route('daycare.index') }}" wire:navigate class="inline-flex items-center gap-1 text-label-md text-primary hover:underline mb-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Data Daycare
            </a>
            <h1 class="text-display-sm font-display-sm text-on-surface">Update Data Daycare</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Perbarui biodata anak daycare yang sudah ada. Tidak membuat anak baru.</p>
        </div>
        <div class="flex items-center gap-2 text-label-md text-on-surface-variant">
            @foreach([1 => 'File', 2 => 'Preview', 3 => 'Selesai'] as $number => $label)
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
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Filter Data Daycare</h2>
                    <p class="text-body-sm text-on-surface-variant mt-1">Filter menentukan anak daycare yang muncul pada file yang akan diunduh.</p>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label for="bulk-update-kelas" class="block text-label-md font-label-md text-on-surface mb-1">Kelas</label>
                        <select id="bulk-update-kelas" wire:model.live="filterKelas" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <option value="">Semua Kelas</option>
                            @foreach($classes as $kelas)
                                <option value="{{ $kelas }}">Kelas {{ $kelas }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="bulk-update-file" class="block text-label-md font-label-md text-on-surface mb-1">File Excel <span class="text-error">*</span></label>
                        <input id="bulk-update-file" type="file" wire:model="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="block w-full text-body-sm text-on-surface-variant file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-primary-fixed file:text-primary file:font-label-md hover:file:bg-primary-fixed/80">
                        <p class="text-body-sm text-on-surface-variant mt-1">Format .xlsx, maksimum 12 MB. Baris kosong dan sel kosong diabaikan.</p>
                        @error('file') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end">
                    <button type="button" wire:click="previewUpdate" wire:loading.attr="disabled" class="bg-primary hover:bg-primary/90 disabled:opacity-60 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">
                        <span wire:loading.remove wire:target="previewUpdate">Preview Perubahan</span>
                        <span wire:loading wire:target="previewUpdate">Memvalidasi...</span>
                    </button>
                </div>
            </section>

            <aside class="bg-surface-container-low border border-outline-variant rounded-2xl p-5 h-fit">
                <div class="w-10 h-10 rounded-xl bg-secondary-container text-on-secondary-container flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <h2 class="text-title-lg font-title-lg text-on-surface">File Update</h2>
                <p class="text-body-sm text-on-surface-variant mt-2">Unduh file yang sudah berisi data anak daycare, lalu sesuaikan biodata pada kolom yang tersedia.</p>
                <button type="button" wire:click="downloadTemplate" class="mt-4 w-full border border-primary text-primary hover:bg-primary-fixed px-4 py-2.5 rounded-xl font-label-lg transition-colors inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">download</span>
                    Download File Daycare
                </button>
                <div class="mt-4 pt-4 border-t border-outline-variant text-body-sm text-on-surface-variant space-y-1.5">
                    <p class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px] mt-0.5">lock</span> ID Sistem tidak boleh diubah.</p>
                    <p class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px] mt-0.5">info</span> Kelas kosong = dipertahankan. Kelas diisi harus A-E.</p>
                    <p class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px] mt-0.5">edit_off</span> Sel kosong = data lama dipertahankan.</p>
                    <p class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px] mt-0.5">close</span> Untuk mengosongkan nilai gunakan form Edit di Data Daycare.</p>
                    <p class="flex items-start gap-2"><span class="material-symbols-outlined text-[18px] mt-0.5">block</span> Fitur ini tidak pernah membuat anak baru.</p>
                </div>
            </aside>
        </div>
    @elseif($step === 2)
        <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-outline-variant flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-headline-sm font-headline-sm text-on-surface">Update Data Daycare - Preview</h2>
                    <p class="text-body-sm text-on-surface-variant mt-1">Hanya baris berstatus SIAP UPDATE yang akan diterapkan.</p>
                </div>
                @if($summary['blocked'] > 0)
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-error-container text-on-error-container text-label-md font-label-md">
                        <span class="material-symbols-outlined text-[18px]">error</span>
                        Perbaiki file sebelum update
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-secondary-container text-on-secondary-container text-label-md font-label-md">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        File siap diperiksa
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 px-6 py-4">
                @foreach([
                    ['value' => $summary['total'], 'label' => 'Total Baris', 'icon' => 'table_rows', 'icon_class' => 'bg-surface-container-high text-on-surface-variant'],
                    ['value' => $summary['ready'], 'label' => 'SIAP UPDATE', 'icon' => 'check_circle', 'icon_class' => 'bg-primary-fixed text-on-primary-fixed'],
                    ['value' => $summary['unchanged'], 'label' => 'TANPA PERUBAHAN', 'icon' => 'remove', 'icon_class' => 'bg-surface-container-high text-on-surface-variant'],
                    ['value' => $summary['blocked'], 'label' => 'GAGAL', 'icon' => 'error', 'icon_class' => 'bg-error-container text-on-error-container'],
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
                <table class="w-full min-w-[1100px] text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-surface-container-low">
                        <tr class="text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">
                            <th class="px-4 py-3">Baris</th>
                            <th class="px-4 py-3">ID Sistem</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Kelas</th>
                            <th class="px-4 py-3 min-w-[300px]">Perubahan</th>
                            <th class="px-4 py-3 min-w-[220px]">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @foreach($rows as $row)
                            <tr class="{{ $row['status'] === 'blocked' ? 'bg-error-container/10' : '' }}">
                                <td class="px-4 py-3 text-body-sm text-on-surface-variant">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-3 text-body-md font-label-md text-on-surface whitespace-nowrap">{{ $row['id_sistem'] }}</td>
                                <td class="px-4 py-3 text-body-md text-on-surface">{{ $row['nama_lengkap'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-body-md text-on-surface whitespace-nowrap">{{ $row['kelas'] ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @if($row['changes'] === [])
                                        <span class="text-body-sm text-on-surface-variant">-</span>
                                    @else
                                        <ul class="space-y-1">
                                            @foreach($row['changes'] as $change)
                                                <li class="text-body-sm leading-snug">
                                                    <span class="text-on-surface-variant">{{ $change['label'] }}:</span>
                                                    <span class="line-through text-on-surface-variant/70">{{ $change['old_display'] }}</span>
                                                    <span class="material-symbols-outlined text-[14px] text-on-surface-variant align-middle">arrow_forward</span>
                                                    <span class="text-on-surface font-label-md">{{ $change['new_display'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle min-w-[220px]">
                                    <div class="flex flex-col items-start justify-center gap-1">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-label-sm font-label-sm leading-5 {{ match($row['status']) {
                                            'ready' => 'bg-primary-container text-on-primary-container',
                                            'unchanged' => 'bg-surface-container-high text-on-surface-variant',
                                            default => 'bg-error-container text-on-error-container',
                                        } }}">
                                            {{ $row['status_label'] }}
                                        </span>
                                        @if($row['errors'] !== [])
                                            <p class="text-body-sm leading-snug text-error">{{ implode(' ', $row['errors']) }}</p>
                                        @endif
                                        @if($row['warnings'] !== [])
                                            <p class="text-body-sm leading-snug text-on-surface-variant">{{ implode(' ', $row['warnings']) }}</p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('update')
                <div class="mx-6 mb-4 rounded-xl bg-error-container text-on-error-container px-4 py-3 text-body-sm">{{ $message }}</div>
            @enderror

            <div class="px-6 py-4 border-t border-outline-variant bg-surface flex flex-col-reverse sm:flex-row justify-end gap-3 sticky bottom-0">
                <button type="button" wire:click="backToSetup" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container rounded-xl transition-colors">Ganti File</button>
                <button type="button" wire:click="confirmUpdate" wire:loading.attr="disabled" @disabled($summary['ready'] === 0) class="bg-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">
                    <span wire:loading.remove wire:target="confirmUpdate">Update {{ $summary['ready'] }} Anak</span>
                    <span wire:loading wire:target="confirmUpdate">Memperbarui...</span>
                </button>
            </div>
        </section>
    @else
        <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-8 text-center max-w-2xl mx-auto">
            <div class="w-16 h-16 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center mx-auto">
                <span class="material-symbols-outlined text-[34px]">check_circle</span>
            </div>
            <h2 class="text-headline-md font-headline-md text-on-surface mt-4">Update Selesai</h2>
            <p class="text-title-lg text-on-surface mt-2">{{ $result['updated'] }} anak daycare diperbarui</p>

            <div class="grid grid-cols-1 gap-3 mt-6 text-left">
                <div class="bg-primary-container rounded-xl p-4">
                    <p class="text-headline-sm font-headline-sm text-on-primary-container">{{ $result['updated'] }}</p>
                    <p class="text-body-sm text-on-primary-container">Biodata anak daycare diperbarui</p>
                </div>
            </div>

            <div class="mt-5 text-left text-body-sm text-on-surface-variant space-y-1.5 bg-surface-container-low rounded-xl p-4">
                <p class="font-label-md text-on-surface">Catatan</p>
                <p>• ID anak tidak berubah sehingga seluruh pembayaran tetap terhubung.</p>
                <p>• Fitur ini hanya memperbarui data anak daycare yang sudah ada dan tidak pernah membuat data baru.</p>
            </div>

            <a href="{{ route('daycare.index') }}" wire:navigate class="mt-6 inline-flex items-center justify-center bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors">
                Kembali ke Data Daycare
            </a>
        </section>
    @endif
</div>