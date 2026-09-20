<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div class="min-w-0">
            <h1 class="text-display-sm font-display-sm text-on-surface">Data Calon Siswa</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola pendaftar calon siswa untuk tahun ajaran tujuan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tambah Calon Siswa
            </button>
            <a href="{{ route('calon-siswa.import') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[20px]">upload_file</span>
                Import Calon Siswa
            </a>
            <a href="{{ route('calon-siswa.update') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
                Update Data Calon Siswa
            </a>
        </div>
    </div>

    @if(session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    @if(session()->has('error'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-8" class="fixed top-24 right-8 z-50 bg-error-container border border-error text-on-error-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-body-md">{{ session('error') }}</p>
        </div>
    @endif

    <section class="grid grid-cols-1 sm:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md">
            <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                <span class="material-symbols-outlined">person_add</span>
            </div>
            <p class="text-on-surface-variant text-body-md mt-4">Total Calon Siswa</p>
            <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format((\App\Models\ProspectiveStudent::query()->count()), 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md">
            <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                <span class="material-symbols-outlined">how_to_reg</span>
            </div>
            <p class="text-on-surface-variant text-body-md mt-4">Terdaftar</p>
            <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format($totalRegistered, 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md">
            <div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                <span class="material-symbols-outlined">check_circle</span>
            </div>
            <p class="text-on-surface-variant text-body-md mt-4">Dikonversi</p>
            <p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format($totalConverted, 0, ',', '.') }}</p>
        </div>
    </section>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
<div class="p-5 border-b border-outline-variant flex flex-col gap-4">
                <div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Cari & Filter Calon Siswa</h3>
                    <p class="text-body-sm text-on-surface-variant mt-1">Cari atau filter berdasarkan tahun ajaran, jenjang, kelas tujuan, status pendaftaran, dan status tagihan.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative flex-1 sm:flex-initial sm:w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nomor pendaftaran, nama, orang tua, HP..." class="w-full h-11 pl-10 pr-4 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md">
                    </div>
                    <select wire:model.live="filterAcademicYearId" class="w-full sm:w-40 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach($academicYears as $academicYear)
                            <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterLevel" class="w-full sm:w-32 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Jenjang</option>
                        @foreach($levels as $level)
                            <option value="{{ $level->value }}">{{ $level->value }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterClassId" class="w-full sm:w-44 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Kelas</option>
                        @foreach($filterClasses as $class)
                            <option value="{{ $class->id }}">{{ $class->name }} (Tingkat {{ $class->level }})</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterStatus" class="w-full sm:w-44 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Status</option>
                        @foreach($registrationStatuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterBillStatus" class="w-full sm:w-44 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm text-body-md py-2">
                        <option value="">Semua Tagihan</option>
                        @foreach($billStatusOptions as $billStatusValue => $billStatusLabel)
                            <option value="{{ $billStatusValue }}">{{ $billStatusLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead>
                    <tr class="bg-surface border-b border-outline-variant text-body-sm font-label-md text-on-surface-variant uppercase tracking-wider">
                        <th class="p-4 w-14 text-center">No.</th>
                        <th class="p-4">No. Pendaftaran</th>
                        <th class="p-4">Nama</th>
                        <th class="p-4">Tahun Ajaran Tujuan</th>
                        <th class="p-4">Kelas Tujuan</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Tagihan</th>
                        <th class="p-4">Orang Tua</th>
                        <th class="p-4">No. HP</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/50 text-body-md text-on-surface">
                    @forelse($prospectiveStudents as $prospectiveStudent)
                        <tr wire:key="prospective-student-{{ $prospectiveStudent->id }}" class="hover:bg-surface-container-low transition-colors">
                            <td class="p-4 text-center text-on-surface-variant font-numeric-data">{{ ($prospectiveStudents->currentPage() - 1) * $prospectiveStudents->perPage() + $loop->iteration }}</td>
                            <td class="p-4 font-semibold font-numeric-data text-primary">{{ $prospectiveStudent->registration_number }}</td>
                            <td class="p-4">
                                <p class="font-semibold">{{ $prospectiveStudent->nama_lengkap }}</p>
                                @if($prospectiveStudent->nama_panggilan)
                                    <p class="text-body-sm text-on-surface-variant">{{ $prospectiveStudent->nama_panggilan }}</p>
                                @endif
                            </td>
                            <td class="p-4 text-on-surface-variant">{{ $prospectiveStudent->academicYear?->year ?? '—' }}</td>
                            <td class="p-4">{{ $prospectiveStudent->schoolClass?->name ?? 'Belum Ditentukan' }}</td>
                            <td class="p-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm {{ $prospectiveStudent->status->value === 'registered' ? 'bg-secondary-fixed text-on-secondary-fixed' : ($prospectiveStudent->status->value === 'converted' ? 'bg-primary-fixed text-on-primary-fixed' : 'bg-error-container text-on-error-container') }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $prospectiveStudent->status->value === 'registered' ? 'bg-secondary' : ($prospectiveStudent->status->value === 'converted' ? 'bg-primary' : 'bg-error') }}"></span>
                                    {{ $prospectiveStudent->status_label }}
                                </span>
                            </td>
                            <td class="p-4">
                                @php($billStatus = $prospectiveStudent->bill_status)
                                @if($billStatus === 'none')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm bg-surface-container-high text-on-surface-variant">Belum Ada Tagihan</span>
                                @elseif($billStatus === \App\Models\ProspectiveStudentBill::STATUS_PARTIAL)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm bg-yellow-100 text-yellow-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span>Sebagian
                                    </span>
                                @elseif($billStatus === \App\Models\ProspectiveStudentBill::STATUS_PAID)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm bg-tertiary-fixed text-on-tertiary-fixed">
                                        <span class="w-1.5 h-1.5 rounded-full bg-on-tertiary-fixed"></span>Lunas
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm bg-error-container text-on-error-container">
                                        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>Belum Bayar
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-on-surface-variant">{{ $prospectiveStudent->nama_orang_tua ?? '—' }}</td>
                            <td class="p-4 text-on-surface-variant font-numeric-data">{{ $prospectiveStudent->no_telp_orang_tua ?? '—' }}</td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('calon-siswa.show', $prospectiveStudent) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg" title="Lihat Detail">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    @if(! $prospectiveStudent->isConverted())
                                        <button type="button" wire:click="edit({{ $prospectiveStudent->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg" title="Edit">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </button>
                                        @if($prospectiveStudent->status === \App\Enums\ProspectiveStudentStatus::Registered && $prospectiveStudent->converted_student_id === null)
                                            <button type="button" wire:click="convertProspect({{ $prospectiveStudent->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg" title="Jadikan Siswa">
                                                <span class="material-symbols-outlined text-[20px]">school</span>
                                            </button>
                                        @endif
                                        <button type="button" wire:click="confirmDelete({{ $prospectiveStudent->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg" title="Hapus">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-10 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl block mb-2">person_search</span>
                                Tidak ada calon siswa yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-outline-variant">
            {{ $prospectiveStudents->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">
                        {{ $isEditing ? 'Edit Data Calon Siswa' : 'Tambah Calon Siswa Baru' }}
                    </h3>
                    <button type="button" wire:click="closeModal" class="text-on-surface-variant hover:text-error p-1">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <form wire:submit="save" class="contents">
                    <div class="p-6 overflow-y-auto flex flex-col gap-5">
                        @if($isEditing)
                            <div class="px-4 py-3 rounded-lg bg-surface-container text-on-surface-variant text-body-sm">
                                No. Pendaftaran: <strong class="text-on-surface font-semibold">{{ $registration_number }}</strong>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="sm:col-span-2">
                                <label for="nama_lengkap" class="block text-label-md font-label-md text-on-surface mb-1">Nama Lengkap <span class="text-error">*</span></label>
                                <input type="text" id="nama_lengkap" wire:model="nama_lengkap" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Lengkap Calon Siswa">
                                @error('nama_lengkap') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="nama_panggilan" class="block text-label-md font-label-md text-on-surface mb-1">Nama Panggilan</label>
                                <input type="text" id="nama_panggilan" wire:model="nama_panggilan" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Panggilan">
                                @error('nama_panggilan') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>

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

                            <div>
                                <label for="academic_year_id" class="block text-label-md font-label-md text-on-surface mb-1">Tahun Ajaran Tujuan <span class="text-error">*</span></label>
                                <select id="academic_year_id" wire:model="academic_year_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Pilih Tahun Ajaran --</option>
                                    @foreach($academicYears as $academicYear)
                                        <option value="{{ $academicYear->id }}">{{ $academicYear->year }}</option>
                                    @endforeach
                                </select>
                                @error('academic_year_id') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="school_class_id" class="block text-label-md font-label-md text-on-surface mb-1">Kelas Tujuan <span class="text-error">*</span></label>
                                <select id="school_class_id" wire:model="school_class_id" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="">-- Belum Ditentukan --</option>
                                    @foreach($schoolClasses as $schoolClass)
                                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}{{ $schoolClass->school_level ? ' — Jenjang '.$schoolClass->school_level->value : '' }}</option>
                                    @endforeach
                                </select>
                                <p class="text-body-sm text-on-surface-variant mt-1">Jenjang calon siswa diturunkan dari kelas tujuan yang dipilih.</p>
                                @error('school_class_id') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="nama_orang_tua" class="block text-label-md font-label-md text-on-surface mb-1">Nama Orang Tua</label>
                                <input type="text" id="nama_orang_tua" wire:model="nama_orang_tua" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Nama Orang Tua / Wali">
                                @error('nama_orang_tua') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="no_telp_orang_tua" class="block text-label-md font-label-md text-on-surface mb-1">No. HP Orang Tua</label>
                                <input type="text" id="no_telp_orang_tua" wire:model="no_telp_orang_tua" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: 081234567890">
                                @error('no_telp_orang_tua') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="alamat" class="block text-label-md font-label-md text-on-surface mb-1">Alamat</label>
                            <textarea id="alamat" wire:model="alamat" rows="3" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Jalan, RT/RW, Desa/Kelurahan..."></textarea>
                            @error('alamat') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="notes" class="block text-label-md font-label-md text-on-surface mb-1">Catatan</label>
                            <textarea id="notes" wire:model="notes" rows="2" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Catatan tambahan (opsional)"></textarea>
                            @error('notes') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3">
                        <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container rounded-xl">Batal</button>
                        <button type="submit" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-colors shadow-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($isDeleteModalOpen && $deletingId)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-on-surface/40 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" x-data x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex flex-col items-center text-center px-6 pt-8 pb-4">
                    <div class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-error" style="font-size: 32px;">delete_forever</span>
                    </div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Calon Siswa?</h3>
                    <p class="text-body-md text-on-surface-variant mt-2 leading-relaxed">
                        Apakah Anda yakin ingin menghapus calon siswa <strong>{{ $deletingName }}</strong>
                        (<span class="font-numeric-data">{{ $deletingRegistrationNumber }}</span>)?
                    </p>
                    <p class="text-body-sm text-error mt-2">
                        Setelah dihapus, data calon siswa tidak dapat dikembalikan.
                    </p>
                </div>
                <div class="flex gap-3 px-6 pb-6 pt-2">
                    <button type="button" wire:click="cancelDelete" class="flex-1 px-4 py-2.5 text-on-surface-variant font-label-lg border border-outline-variant rounded-xl hover:bg-surface-container transition-colors">Batal</button>
                    <button type="button" wire:click="delete" class="flex-1 px-4 py-2.5 bg-error hover:bg-error/90 text-on-error font-label-lg rounded-xl transition-colors shadow-sm">Hapus</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.prospective-student.convert-modal', ['prospectiveStudent' => $this->prospectiveStudent ?? null])
</div>