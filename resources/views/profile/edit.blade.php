<x-app-layout>
    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-surface-container-lowest shadow-sm sm:rounded-xl border border-outline-variant p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="bg-surface-container-lowest shadow-sm sm:rounded-xl border border-outline-variant p-6">
                @include('profile.partials.update-password-form')
            </div>

            <div class="bg-surface-container-lowest shadow-sm sm:rounded-xl border border-outline-variant p-6">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
