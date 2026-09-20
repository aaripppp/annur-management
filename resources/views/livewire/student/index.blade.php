<div>
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Data Siswa</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola data siswa, filter berdasarkan kelas, dan tambah data baru.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tambah Siswa
            </button>
            <a href="{{ route('siswa.import') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
                <span class="material-symbols-outlined text-[20px]">upload_file</span>
                Import Siswa
            </a>
            <a href="{{ route('siswa.update') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
                Update Data Siswa
            </a>
        </div>
    </div>

    <!-- Summary Cards Row -->
    <section class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-gutter mb-stack-lg">
        <!-- Card 1: Total Siswa -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">groups</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Siswa</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($totalStudents, 0, ',', '.') }} Siswa</p>
            </div>
        </div>

        <!-- Card 2: Siswa Laki-laki -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">man</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Laki-laki</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($totalMale, 0, ',', '.') }} Siswa</p>
            </div>
        </div>

        <!-- Card 3: Siswa Perempuan -->
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2 hover:shadow-sm transition-shadow duration-300">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-error-container flex items-center justify-center text-error">
                    <span class="material-symbols-outlined">woman</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Perempuan</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data tracking-wider">{{ number_format($totalFemale, 0, ',', '.') }} Siswa</p>
            </div>
        </div>
    </section>

    <!-- Toast Alert Success -->
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
             class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Toast Alert Error -->
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
             class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Content Area -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <!-- Toolbar (Filter & Search) -->
        <div class="p-6 border-b border-outline-variant flex flex-col sm:flex-row justify-between items-center gap-4">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Siswa</h2>
            
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-initial">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Cari nama, NIS, atau kelas..."
                           class="w-full sm:w-64 pl-10 pr-4 py-2 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md">
                </div>
                <span class="material-symbols-outlined text-on-surface-variant">filter_list</span>
                <select wire:model.live="filterLevel" class="w-full sm:w-36 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                    <option value="">Semua Jenjang</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->value }}">{{ $level->value }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterClassId" class="w-full sm:w-44 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                    <option value="">Semua Kelas</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} (Tingkat {{ $c->level }})</option>
                    @endforeach
                </select>
                <select wire:model.live="filterStatus" class="w-full sm:w-44 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                    <option value="">Semua Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="calon_siswa">Calon Siswa</option>
                    <option value="lulus">Lulus</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead>
                    <tr class="bg-surface border-b border-outline-variant text-body-sm font-label-md text-on-surface-variant uppercase tracking-wider">
                        <th class="p-4 w-14 text-center font-semibold">No.</th>
                        <th class="p-4 font-semibold">NIS</th>
                        <th class="p-4 font-semibold">Nama Lengkap</th>
                        <th class="p-4 font-semibold">Panggilan</th>
                        <th class="p-4 font-semibold">Kelas</th>
                        <th class="p-4 font-semibold">Status</th>
                        <th class="p-4 font-semibold">L/P</th>
                        <th class="p-4 font-semibold">Alamat</th>
                        <th class="p-4 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-body-md text-on-surface font-body-md divide-y divide-outline-variant/50">
                    @forelse($students as $student)
                        @php
                            $academicStatus = $student->academicStatus();
                            $academicStatusLabel = $student->academicStatusLabel;
                        @endphp
                        <tr wire:key="student-{{ $student->id }}" class="hover:bg-surface-container-low transition-colors group">
                            <td class="p-4 text-center text-on-surface-variant font-numeric-data">{{ ($students->currentPage() - 1) * $students->perPage() + $loop->iteration }}</td>
                            <td class="p-4 font-label-md text-secondary">{{ $student->nis ?: '-' }}</td>
                            <td class="p-4 font-label-md">{{ $student->nama_lengkap }}</td>
                            <td class="p-4 text-on-surface-variant">{{ $student->nama_panggilan ?: '-' }}</td>
                            <td class="p-4">
                                <span class="bg-secondary-fixed text-on-secondary-fixed px-2.5 py-1 rounded-md text-label-sm font-label-sm">
                                    {{ $student->academicClassLabel() }}
                                </span>
                                <p class="text-body-sm text-on-surface-variant mt-1">{{ $student->schoolLevel?->value ?? '-' }} &bull; {{ $student->academicYearContextLabel() ?? '-' }}</p>
                            </td>
                            <td class="p-4">
                                @if($academicStatus === 'lulus')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-tertiary-fixed text-on-tertiary-fixed">
                                        <span class="material-symbols-outlined text-[14px]">school</span>
                                        {{ $academicStatusLabel }}
                                    </span>
                                @elseif($academicStatus === 'calon_siswa')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        <span class="material-symbols-outlined text-[14px]">person_add</span>
                                        {{ $academicStatusLabel }}
                                    </span>
                                @elseif($academicStatus === 'aktif')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-label-sm font-label-sm bg-secondary-fixed text-on-secondary-fixed">
                                        <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                        {{ $academicStatusLabel }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant text-body-sm">-</span>
                                @endif
                            </td>
                            <td class="p-4">
                                @if($student->jenis_kelamin === 'L')
                                    <span class="text-primary font-bold">L</span>
                                @elseif($student->jenis_kelamin === 'P')
                                    <span class="text-error font-bold">P</span>
                                @else
                                    <span class="text-on-surface-variant">-</span>
                                @endif
                            </td>
                            <td class="p-4 text-on-surface-variant max-w-[200px] truncate" title="{{ $student->alamat ?? '' }}">{{ $student->alamat ?: '-' }}</td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('siswa.show', $student->id) }}" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg transition-colors" title="Detail & Tagihan">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    <button wire:click="edit({{ $student->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    <button wire:click="confirmDelete({{ $student->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2">search_off</span>
                                <p>Tidak ada siswa yang ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="p-4 border-t border-outline-variant">
            {{ $students->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    <!-- Modal Form (Create/Edit) -->
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm transition-all" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface" id="modal-title">
                        {{ $isEditing ? 'Edit Data Siswa' : 'Tambah Siswa Baru' }}
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="save" class="flex flex-col gap-5">
                        
                        <!-- NIS & Kelas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="nis" class="block text-label-md font-label-md text-on-surface mb-1">NIS</label>
                                <input type="text" id="nis" wire:model="nis" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: 12345678">
                                @error('nis') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="class_id" class="block text-label-md font-label-md text-on-surface mb-1">Kelas <span class="text-error">*</span></label>
                                <select id="class_id" wire:model="class_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach($classes as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                @error('class_id') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Tahun Ajaran Masuk -->
                        @if(!$isEditing)
                        <div>
                            <label for="entry_academic_year_id" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran Masuk</label>
                            <select id="entry_academic_year_id" wire:model="entry_academic_year_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                <option value="">Tahun Ajaran Aktif Saat Ini</option>
                                @foreach($academicYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->year }}</option>
                                @endforeach
                            </select>
                            <p class="text-body-sm text-on-surface-variant mt-1">Kosongkan jika siswa masuk pada tahun ajaran aktif saat ini.</p>
                            @error('entry_academic_year_id') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                        @endif

                        <div>
                            <label for="entry_date" class="block text-label-md font-label-md text-on-surface mb-1">Tanggal Masuk</label>
                            <input type="date" id="entry_date" wire:model="entry_date" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                            <p class="text-body-sm text-on-surface-variant mt-1">Kosongkan jika mengikuti tanggal data siswa dibuat.</p>
                            @error('entry_date') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nama Lengkap & Panggilan -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="nama_lengkap" class="block text-label-md font-label-md text-on-surface mb-1">Nama Lengkap <span class="text-error">*</span></label>
                                <input type="text" id="nama_lengkap" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Lengkap Siswa" required>
                                @error('nama_lengkap') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="nama_panggilan" class="block text-label-md font-label-md text-on-surface mb-1">Nama Panggilan</label>
                                <input type="text" id="nama_panggilan" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Panggilan">
                                @error('nama_panggilan') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        @include('livewire.student.profile-fields', ['fieldPrefix' => 'student-form'])

                        <!-- Jenis Kelamin -->
                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-2">Jenis Kelamin</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" wire:model="jenis_kelamin" value="L" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Laki-laki (L)</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="jenis_kelamin" wire:model="jenis_kelamin" value="P" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Perempuan (P)</span>
                                </label>
                            </div>
                            @error('jenis_kelamin') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Alamat -->
                        <div>
                            <label for="alamat" class="block text-label-md font-label-md text-on-surface mb-1">Alamat Lengkap</label>
                            <textarea id="alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Jalan, RT/RW, Desa/Kelurahan..."></textarea>
                            @error('alamat') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3 sticky bottom-0">
                    <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container transition-colors rounded-xl">
                        Batal
                    </button>
                    <button type="button" wire:click="save" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($isDeleteModalOpen)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden"
                 x-data
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete_forever</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Siswa?</h3>
                    @if($deletingIsConverted)
                        <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                            Menghapus siswa ini juga akan menghapus data calon siswa asal, tagihan pendaftaran, pembayaran formulir, dan seluruh riwayat terkait. Data tidak dapat dikembalikan.
                        </p>
                    @else
                        <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                            Tindakan ini tidak bisa dibatalkan.<br>
                            <strong class="text-error">Seluruh data pembayaran</strong> milik siswa ini juga akan ikut dihapus secara permanen.
                        </p>
                    @endif
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button wire:click="cancelDelete" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">
                        Batal
                    </button>
                    <button wire:click="delete" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
