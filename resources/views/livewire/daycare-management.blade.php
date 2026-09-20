<div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div class="min-w-0">
            <h1 class="text-display-sm font-display-sm text-on-surface">Data Daycare</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola data anak daycare, filter berdasarkan kelas, dan tambah data baru.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 lg:flex-nowrap lg:justify-end">
            <button type="button" wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Tambah Anak
            </button>
            <a href="{{ route('daycare.import') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[20px]">upload_file</span>
                Import Daycare
            </a>
            <a href="{{ route('daycare.update') }}" wire:navigate class="border border-primary text-primary hover:bg-primary-fixed px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
                Update Data Daycare
            </a>
        </div>
    </div>

    @if(session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" class="fixed top-24 right-8 z-50 bg-secondary-container border border-secondary text-on-secondary-container px-5 py-4 rounded-xl shadow-lg flex items-center gap-3 min-w-[300px]">
            <span class="material-symbols-outlined text-secondary">check_circle</span>
            <p class="font-body-md">{{ session('success') }}</p>
        </div>
    @endif

    <section>
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-stack-lg">
            <div>
                <h2 class="text-headline-md font-headline-md text-on-surface">Data Anak Daycare</h2>
                <p class="text-body-md text-on-surface-variant mt-1">Kelola biodata anak Daycare.</p>
            </div>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-3 gap-gutter mb-stack-lg">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md"><div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary"><span class="material-symbols-outlined">child_care</span></div><p class="text-on-surface-variant text-body-md mt-4">Total Anak</p><p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format($totalChildren, 0, ',', '.') }}</p></div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md"><div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary"><span class="material-symbols-outlined">check_circle</span></div><p class="text-on-surface-variant text-body-md mt-4">Aktif</p><p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format($totalActive, 0, ',', '.') }}</p></div>
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md"><div class="w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant"><span class="material-symbols-outlined">person_off</span></div><p class="text-on-surface-variant text-body-md mt-4">Nonaktif</p><p class="text-headline-md font-headline-md text-on-surface mt-1 font-numeric-data">{{ number_format($totalInactive, 0, ',', '.') }}</p></div>
        </section>

            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm">
                <div class="p-5 border-b border-outline-variant flex flex-col gap-4">
                    <div><h3 class="text-headline-sm font-headline-sm text-on-surface">Cari Anak</h3><p class="text-body-sm text-on-surface-variant mt-1">Cari berdasarkan nama lengkap atau nama panggilan.</p></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[minmax(20rem,1fr)_10rem_10rem] gap-3 w-full">
                        <div class="relative sm:col-span-2 lg:col-span-1"><span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama lengkap atau nama panggilan..." class="w-full h-11 pl-10 pr-4 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"></div>
                        <select wire:model.live="filterClass" class="w-full h-11 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"><option value="">Semua Kelas</option>@foreach(['A', 'B', 'C', 'D', 'E'] as $classOption)<option value="{{ $classOption }}">Kelas {{ $classOption }}</option>@endforeach</select>
                        <select wire:model.live="filterStatus" class="w-full h-11 border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm"><option value="">Semua Status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead><tr class="bg-surface border-b border-outline-variant text-body-sm font-label-md text-on-surface-variant uppercase tracking-wider"><th class="p-4 w-14 text-center">No.</th><th class="p-4">Nama</th><th class="p-4">Panggilan</th><th class="p-4">Kelas</th><th class="p-4">Status</th><th class="p-4 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-outline-variant/50 text-body-md text-on-surface">
                            @forelse($children as $child)
                                <tr wire:key="daycare-child-{{ $child->id }}" class="hover:bg-surface-container-low transition-colors">
                                    <td class="p-4 text-center text-on-surface-variant font-numeric-data">{{ ($children->currentPage() - 1) * $children->perPage() + $loop->iteration }}</td>
                                    <td class="p-4 font-semibold">{{ $child->nama_lengkap }}</td>
                                    <td class="p-4 text-on-surface-variant">{{ $child->nama_panggilan }}</td>
                                    <td class="p-4">Kelas {{ $child->kelas }}</td>
                                    <td class="p-4"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-label-sm {{ $child->is_active ? 'bg-secondary-fixed text-on-secondary-fixed' : 'bg-surface-container-high text-on-surface-variant' }}"><span class="w-1.5 h-1.5 rounded-full {{ $child->is_active ? 'bg-secondary' : 'bg-on-surface-variant' }}"></span>{{ $child->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td class="p-4 text-right"><div class="flex justify-end gap-1"><a href="{{ route('daycare.show', $child) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg" title="Detail"><span class="material-symbols-outlined text-[20px]">visibility</span></a><a href="{{ route('daycare.show', ['child' => $child, 'edit' => 1]) }}" wire:navigate class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary-fixed/50 rounded-lg" title="Edit Biodata"><span class="material-symbols-outlined text-[20px]">edit</span></a><button type="button" wire:click="confirmChildDelete({{ $child->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg" title="Hapus"><span class="material-symbols-outlined text-[20px]">delete</span></button></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-10 text-center text-on-surface-variant"><span class="material-symbols-outlined text-4xl block mb-2">person_search</span>Tidak ada anak Daycare yang ditemukan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t border-outline-variant">{{ $children->links(data: ['scrollTo' => false]) }}</div>
            </div>
        </section>

    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface"><h3 class="text-headline-sm font-headline-sm text-on-surface">Tambah Anak Daycare</h3><button type="button" wire:click="closeModal" class="text-on-surface-variant hover:text-error p-1"><span class="material-symbols-outlined">close</span></button></div>
                <form wire:submit="save" class="contents"><div class="p-6 overflow-y-auto flex flex-col gap-5">@include('livewire.daycare-child-fields', ['mode' => 'create'])</div><div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3"><button type="button" wire:click="closeModal" class="px-5 py-2.5 text-on-surface-variant font-label-lg hover:bg-surface-container rounded-xl">Batal</button><button type="submit" class="bg-primary hover:bg-primary/90 text-on-primary px-6 py-2.5 rounded-xl font-label-lg">Simpan</button></div></form>
            </div>
        </div>
    @endif

    @if($isChildDeleteModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="p-6">
                    <div class="w-12 h-12 rounded-full bg-error-container text-error flex items-center justify-center mb-4"><span class="material-symbols-outlined">delete_forever</span></div>
                    <h3 class="text-headline-sm font-headline-sm text-on-surface">Hapus Data Anak?</h3>
                    @if($deletingChildPaymentCount > 0)
                        <div class="mt-3 px-4 py-3 rounded-lg bg-error-container text-on-error-container">
                            <p class="text-body-md font-semibold">{{ $deletingChildName }} memiliki {{ $deletingChildPaymentCount }} riwayat transaksi dengan total Rp {{ number_format($deletingChildPaymentTotal, 0, ',', '.') }}.</p>
                            <p class="text-body-sm mt-2">Jika data anak dihapus, seluruh riwayat transaksi, rincian pembayaran, bukti transfer, dan data terkait transaksi anak ini juga akan dihapus secara permanen.</p>
                            <p class="text-body-sm font-semibold mt-2">Data yang sudah dihapus tidak dapat dikembalikan.</p>
                        </div>
                    @else
                        <p class="text-body-md text-on-surface-variant mt-2">Data {{ $deletingChildName }} akan dihapus secara permanen.</p>
                    @endif
                </div>
                <div class="px-6 py-4 border-t border-outline-variant bg-surface flex justify-end gap-3"><button type="button" wire:click="cancelChildDelete" class="px-5 py-2.5 text-on-surface-variant rounded-xl hover:bg-surface-container">Batal</button><button type="button" wire:click="deleteChild" class="px-5 py-2.5 bg-error text-on-error rounded-xl font-label-lg hover:opacity-90">{{ $deletingChildPaymentCount > 0 ? 'Hapus Semua Data' : 'Hapus' }}</button></div>
            </div>
        </div>
    @endif
</div>
