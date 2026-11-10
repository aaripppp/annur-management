@php($passwordErrors = $errors->getBag('updatePassword'))

<section>
    <header>
        <h2 class="text-headline-sm font-headline-sm text-on-surface">
            {{ __('Ubah Password') }}
        </h2>
        <p class="mt-1 text-body-sm text-on-surface-variant">
            {{ __('Pastikan akun Anda menggunakan password yang panjang dan acak untuk menjaga keamanan.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="current_password" :value="__('Password Lama')" />
            <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full {{ $passwordErrors->has('current_password') ? 'border-error focus:border-error focus:ring-error' : '' }}" autocomplete="current-password" :aria-invalid="$passwordErrors->has('current_password') ? 'true' : 'false'" aria-describedby="current-password-error" />
            <x-input-error id="current-password-error" role="alert" :messages="$passwordErrors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password Baru')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full {{ $passwordErrors->has('password') ? 'border-error focus:border-error focus:ring-error' : '' }}" autocomplete="new-password" :aria-invalid="$passwordErrors->has('password') ? 'true' : 'false'" aria-describedby="password-error" />
            <x-input-error id="password-error" role="alert" :messages="$passwordErrors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full {{ $passwordErrors->has('password_confirmation') ? 'border-error focus:border-error focus:ring-error' : '' }}" autocomplete="new-password" :aria-invalid="$passwordErrors->has('password_confirmation') ? 'true' : 'false'" aria-describedby="password-confirmation-error" />
            <x-input-error id="password-confirmation-error" role="alert" :messages="$passwordErrors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Simpan') }}</x-primary-button>
        </div>
    </form>
</section>
