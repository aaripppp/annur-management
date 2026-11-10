@php($profileErrors = $errors->getBag('updateProfile'))

<section>
    <header>
        <h2 class="text-headline-sm font-headline-sm text-on-surface">
            {{ __('Informasi Profil') }}
        </h2>
        <p class="mt-1 text-body-sm text-on-surface-variant">
            {{ __('Perbarui nama, email, dan jabatan Anda.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Nama Lengkap')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full {{ $profileErrors->has('name') ? 'border-error focus:border-error focus:ring-error' : '' }}" :value="old('name', $user->name)" required autofocus autocomplete="name" :aria-invalid="$profileErrors->has('name') ? 'true' : 'false'" aria-describedby="profile-name-error" />
                <x-input-error id="profile-name-error" role="alert" class="mt-2" :messages="$profileErrors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full {{ $profileErrors->has('email') ? 'border-error focus:border-error focus:ring-error' : '' }}" :value="old('email', $user->email)" required autocomplete="username" :aria-invalid="$profileErrors->has('email') ? 'true' : 'false'" aria-describedby="profile-email-error" />
                <x-input-error id="profile-email-error" role="alert" class="mt-2" :messages="$profileErrors->get('email')" />
            </div>

            <div class="md:col-span-2">
                <x-input-label for="position" :value="__('Jabatan')" />
                @if ($user->hasReservedPosition())
                    <x-text-input id="position" type="text" class="mt-1 block w-full bg-surface-container-low text-on-surface-variant" :value="$user->position" disabled />
                    <p class="mt-2 text-body-sm text-on-surface-variant">Jabatan ini memiliki kewenangan laporan keuangan dan hanya dapat diubah oleh Superadmin.</p>
                @else
                    <x-text-input id="position" name="position" type="text" class="mt-1 block w-full {{ $profileErrors->has('position') ? 'border-error focus:border-error focus:ring-error' : '' }}" :value="old('position', $user->position)" maxlength="255" autocomplete="organization-title" :aria-invalid="$profileErrors->has('position') ? 'true' : 'false'" aria-describedby="profile-position-error" />
                    <x-input-error id="profile-position-error" role="alert" class="mt-2" :messages="$profileErrors->get('position')" />
                @endif
            </div>

            <div class="md:col-span-2">
                <x-input-label for="current_password_profile" :value="__('Password Saat Ini')" />
                <x-text-input id="current_password_profile" name="current_password" type="password" class="mt-1 block w-full {{ $profileErrors->has('current_password') ? 'border-error focus:border-error focus:ring-error' : '' }}" autocomplete="current-password" :aria-invalid="$profileErrors->has('current_password') ? 'true' : 'false'" aria-describedby="profile-current-password-help profile-current-password-error" />
                <p id="profile-current-password-help" class="mt-2 text-body-sm text-on-surface-variant">Wajib diisi hanya ketika Anda mengubah alamat email.</p>
                <x-input-error id="profile-current-password-error" role="alert" class="mt-2" :messages="$profileErrors->get('current_password')" />
            </div>
        </div>

        <div class="border-t border-outline-variant pt-6">
            <h3 class="text-label-lg font-label-lg text-on-surface">Informasi Akun</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-surface-container-low px-4 py-3">
                    <p class="text-label-sm text-on-surface-variant">Role</p>
                    <p class="mt-1 text-body-md font-label-md text-on-surface">{{ $user->roleLabel() }}</p>
                </div>
                @if ($user->isTuUnit())
                    <div class="rounded-xl bg-surface-container-low px-4 py-3">
                        <p class="text-label-sm text-on-surface-variant">Unit Sekolah</p>
                        <p class="mt-1 text-body-md font-label-md text-on-surface">{{ $user->school_level?->value ?? '-' }}</p>
                    </div>
                @endif
            </div>
            <p class="mt-3 text-body-sm text-on-surface-variant">Role, unit sekolah, dan status akun hanya dapat diubah melalui Manajemen Akun.</p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Simpan') }}</x-primary-button>
        </div>
    </form>
</section>
