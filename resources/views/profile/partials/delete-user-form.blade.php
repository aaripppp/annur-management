<section class="space-y-6">
    <header>
        <h2 class="text-headline-sm font-headline-sm text-on-surface">
            {{ __('Hapus Akun') }}
        </h2>
        <p class="mt-1 text-body-sm text-on-surface-variant">
            {{ __('Hapus akun Anda secara permanen. Setelah akun dihapus, semua sumber daya dan datanya akan dihapus secara permanen. Sebelum menghapus akun, silakan unduh data atau informasi apa pun yang ingin Anda simpan.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click="$dispatch('open-modal')"
    >
        {{ __('Hapus Akun') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()">
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-headline-sm font-headline-sm text-on-surface">
                {{ __('Yakin ingin menghapus akun?') }}
            </h2>

            <p class="mt-1 text-body-sm text-on-surface-variant">
                {{ __('Masukkan password Anda untuk mengkonfirmasi penghapusan akun secara permanen.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Password" class="sr-only" />
                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="Password"
                    autocomplete="current-password"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$el.closest('[x-data]').__x.$data.show = false">
                    {{ __('Batal') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Hapus Akun') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
