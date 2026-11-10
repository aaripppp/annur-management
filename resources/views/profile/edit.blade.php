<x-app-layout>
    <div class="py-4 lg:py-6">
        <div class="mx-auto max-w-3xl space-y-6">
            <div>
                <h1 class="text-display-sm font-display-sm text-on-surface">Profil Saya</h1>
                <p class="mt-1 text-body-md text-on-surface-variant">Kelola informasi pribadi dan keamanan akun Anda.</p>
            </div>

            <div class="bg-surface-container-lowest shadow-sm sm:rounded-xl border border-outline-variant p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="bg-surface-container-lowest shadow-sm sm:rounded-xl border border-outline-variant p-6">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
