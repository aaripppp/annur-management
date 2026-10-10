<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Kategori Siswa</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola kategori khusus yang dapat diberikan kepada siswa.</p>
        </div>
        <button wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Kategori
        </button>
    </div>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md">
            <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                <span class="material-symbols-outlined">label</span>
            </div>
            <p class="text-on-surface-variant text-body-md font-body-md mt-4">Total Kategori</p>
            <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format($totalCategories, 0, ',', '.') }} Kategori</p>
        </div>
    </section>

    @if (session()->has('success'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 3000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px] max-w-sm">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-8"
             x-transition:enter-end="opacity-100 translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0"
             x-transition:leave-end="opacity-0 translate-x-8"
             class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px] max-w-sm">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Kategori Siswa</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-3 px-3 w-14 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">No.</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Nama</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Kode</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Siswa</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse($categories as $category)
                        <tr wire:key="student-category-{{ $category->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-3 text-body-md text-on-surface-variant text-center font-numeric-data">{{ ($categories->currentPage() - 1) * $categories->perPage() + $loop->iteration }}</td>
                            <td class="py-4 px-6 text-body-md font-body-md text-on-surface">{{ $category->name }}</td>
                            <td class="py-4 px-6"><code class="text-body-sm bg-surface-container px-2 py-1 rounded">{{ $category->code }}</code></td>
                            <td class="py-4 px-6 text-center">
                                @if($category->is_active)
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aktif</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface"><span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Nonaktif</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center font-numeric-data">{{ number_format($category->students_count, 0, ',', '.') }}</td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $category->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit"><span class="material-symbols-outlined text-[20px]">edit</span></button>
                                    <button wire:click="confirmDelete({{ $category->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-on-surface-variant">Tidak ada kategori siswa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-outline-variant">{{ $categories->links(data: ['scrollTo' => false]) }}</div>
    </div>

    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">{{ $isEditing ? 'Edit Kategori Siswa' : 'Tambah Kategori Siswa' }}</h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-error rounded-lg p-1"><span class="material-symbols-outlined">close</span></button>
                </div>
                <div class="p-6 flex flex-col gap-5">
                    <div>
                        <label for="category-name" class="block text-label-md font-label-md text-on-surface mb-1">Nama <span class="text-error">*</span></label>
                        <input id="category-name" type="text" wire:model="name" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: Anak Guru">
                        @error('name') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="category-code" class="block text-label-md font-label-md text-on-surface mb-1">Kode <span class="text-error">*</span></label>
                        <input id="category-code" type="text" wire:model="code" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="contoh: anak_guru">
                        <p class="text-body-sm text-on-surface-variant mt-1">Kode dinormalisasi ke huruf kecil dan garis bawah.</p>
                        @error('code') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <fieldset>
                        <legend class="block text-label-md font-label-md text-on-surface mb-2">Status</legend>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="category_is_active" wire:model="is_active" value="1" class="text-primary focus:ring-primary border-outline-variant"> Aktif</label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="category_is_active" wire:model="is_active" value="0" class="text-primary focus:ring-primary border-outline-variant"> Nonaktif</label>
                        </div>
                    </fieldset>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3">
                    <button wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl">Batal</button>
                    <button wire:click="save" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4"><span class="material-symbols-outlined text-error text-[32px]">delete_forever</span></div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Kategori Siswa?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2">Kategori hanya dapat dihapus jika belum digunakan oleh siswa.</p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDelete" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl">Batal</button>
                    <button wire:click="delete" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
