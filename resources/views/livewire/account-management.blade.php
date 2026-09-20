<div>
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-stack-lg">
        <div>
            <h1 class="text-display-sm font-display-sm text-on-surface">Manajemen Akun</h1>
            <p class="text-body-md text-on-surface-variant mt-1">Kelola akun pengguna yang dapat mengakses Annur Management.</p>
        </div>
        <button wire:click="openModal" class="bg-primary hover:bg-primary/90 text-on-primary px-5 py-2.5 rounded-xl font-label-lg transition-colors flex items-center gap-2 w-fit">
            <span class="material-symbols-outlined text-[20px]">add</span>
            Tambah Akun
        </button>
    </div>

    <!-- Summary Cards Row -->
    <section class="grid grid-cols-2 md:grid-cols-3 gap-gutter mb-stack-lg">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined">group</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Total Akun</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 tracking-wider font-numeric-data">{{ $totalAccounts }}</p>
            </div>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-stack-md flex flex-col gap-2">
            <div class="flex justify-between items-start">
                <div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">verified_user</span>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-on-surface-variant text-body-md font-body-md">Akun Aktif</p>
                <p class="text-headline-md font-headline-md text-on-surface mt-1 tracking-wider font-numeric-data">{{ $activeAccounts }}</p>
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

    <!-- Desktop Table -->
    <div class="hidden md:block bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-outline-variant flex justify-between items-center">
            <h2 class="text-headline-sm font-headline-sm text-on-surface">Daftar Akun Pengguna</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Nama</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Jabatan</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Email</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Role</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-center">Status</th>
                        <th class="py-3 px-6 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($users as $user)
                        <tr wire:key="user-row-{{ $user->id }}" class="hover:bg-surface-container-lowest/50 transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold text-body-sm">
                                        {{ $user->initials() }}
                                    </div>
                                    <div>
                                        <p class="text-body-md font-body-md text-on-surface">{{ $user->name }}</p>
                                        @if ($user->id === auth()->id())
                                            <p class="text-label-sm text-on-surface-variant">Anda</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-body-md text-on-surface-variant">{{ $user->position ?: '-' }}</td>
                            <td class="py-4 px-6 text-body-md text-on-surface-variant">{{ $user->email }}</td>
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-label-sm font-label-sm {{ $user->isSuperAdmin() ? 'bg-primary/10 text-primary' : 'bg-secondary-container text-on-secondary-container' }}">
                                    {{ $user->roleLabel() }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if ($user->is_active)
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface">
                                        <span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="edit({{ $user->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                    @if ($user->is_active)
                                        <button wire:click="toggleStatus({{ $user->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Nonaktifkan">
                                            <span class="material-symbols-outlined text-[20px]">block</span>
                                        </button>
                                    @else
                                        <button wire:click="toggleStatus({{ $user->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Aktifkan">
                                            <span class="material-symbols-outlined text-[20px]">play_circle</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-4xl mb-2 block">group</span>
                                <p>Tidak ada akun ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards -->
    <div class="md:hidden flex flex-col gap-3">
        @forelse ($users as $user)
            <div wire:key="user-card-{{ $user->id }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-secondary-container text-on-secondary-container flex items-center justify-center font-bold shrink-0">
                            {{ $user->initials() }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-body-md font-body-md text-on-surface truncate">{{ $user->name }}</p>
                            <p class="text-body-sm text-on-surface-variant truncate">{{ $user->position ?: 'Tanpa jabatan' }}</p>
                            <p class="text-body-sm text-on-surface-variant truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    @if ($user->is_active)
                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm bg-primary-fixed text-on-primary-fixed shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm bg-surface-container-high text-on-surface shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Nonaktif
                        </span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-label-sm font-label-sm {{ $user->isSuperAdmin() ? 'bg-primary/10 text-primary' : 'bg-secondary-container text-on-secondary-container' }}">
                        {{ $user->roleLabel() }}
                    </span>
                    <div class="flex items-center gap-1">
                        <button wire:click="edit({{ $user->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </button>
                        @if ($user->is_active)
                            <button wire:click="toggleStatus({{ $user->id }})" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-lg transition-colors" title="Nonaktifkan">
                                <span class="material-symbols-outlined text-[20px]">block</span>
                            </button>
                        @else
                            <button wire:click="toggleStatus({{ $user->id }})" class="p-2 text-on-surface-variant hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Aktifkan">
                                <span class="material-symbols-outlined text-[20px]">play_circle</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-8 text-center text-on-surface-variant">
                <span class="material-symbols-outlined text-4xl mb-2 block">group</span>
                <p>Tidak ada akun ditemukan.</p>
            </div>
        @endforelse
    </div>

    <div class="p-4">
        {{ $users->links(data: ['scrollTo' => false]) }}
    </div>

    <!-- Modal Form (Create/Edit) -->
    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-on-surface/30 backdrop-blur-sm" aria-labelledby="account-modal-title" role="dialog" aria-modal="true">
            <div class="bg-surface-container-lowest rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface sticky top-0">
                    <h3 class="text-headline-sm font-headline-sm text-on-surface" id="account-modal-title">
                        {{ $isEditing ? 'Edit Akun' : 'Tambah Akun' }}
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-error rounded-lg p-1 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form wire:submit="save" class="flex flex-col gap-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="name" class="block text-label-md font-label-md text-on-surface mb-1">Nama Lengkap <span class="text-error">*</span></label>
                                <input type="text" id="name" wire:model="name" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: Windiarti">
                                @error('name') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="email" class="block text-label-md font-label-md text-on-surface mb-1">Email <span class="text-error">*</span></label>
                                <input type="email" id="email" wire:model="email" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="contoh@email.com">
                                @error('email') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="password" class="block text-label-md font-label-md text-on-surface mb-1">
                                    {{ $isEditing ? 'Password Baru' : 'Password' }}
                                    @if (! $isEditing)<span class="text-error">*</span>@endif
                                </label>
                                <input type="password" id="password" wire:model="password" autocomplete="new-password" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @if ($isEditing)
                                    <p class="text-body-sm text-on-surface-variant mt-1">Kosongkan jika tidak ingin mengubah password.</p>
                                @endif
                                @error('password') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-label-md font-label-md text-on-surface mb-1">
                                    {{ $isEditing ? 'Konfirmasi Password Baru' : 'Konfirmasi Password' }}
                                    @if (! $isEditing)<span class="text-error">*</span>@endif
                                </label>
                                <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                @error('password_confirmation') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="role" class="block text-label-md font-label-md text-on-surface mb-1">Role <span class="text-error">*</span></label>
                                <select id="role" wire:model="role" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm">
                                    <option value="{{ \App\Models\User::ROLE_ADMIN }}">Admin</option>
                                    <option value="{{ \App\Models\User::ROLE_SUPER_ADMIN }}">Super Admin</option>
                                </select>
                                @error('role') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="position" class="block text-label-md font-label-md text-on-surface mb-1">Jabatan</label>
                                <input type="text" id="position" wire:model="position" class="w-full border-outline-variant focus:border-primary focus:ring-primary rounded-lg shadow-sm" placeholder="Contoh: Direktur Keuangan, Kepala Tata Usaha">
                                @error('position') <span class="text-error text-body-sm mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-label-md font-label-md text-on-surface mb-2">Status Aktif <span class="text-error">*</span></label>
                            <div class="flex gap-4 items-center h-[42px]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="is_active" wire:model="is_active" value="1" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Aktif</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="is_active" wire:model="is_active" value="0" class="text-primary focus:ring-primary border-outline-variant">
                                    <span class="text-body-md text-on-surface">Nonaktif</span>
                                </label>
                            </div>
                            @error('is_active') <span class="text-error text-body-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </form>
                </div>
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
</div>