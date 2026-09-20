@props(['name' => null])

<div
    x-data="{ show: false }"
    @if($name) x-on:{{ $name }}.window="show = true" @endif
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[9999] overflow-y-auto"
    style="display: none;"
>
    <div class="fixed inset-0 bg-inverse-surface/60 transition-opacity" x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-on:click="show = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div
            class="w-full max-w-lg bg-surface-container-lowest rounded-xl shadow-lg border border-outline-variant p-6 relative"
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-on:click.outside="show = false"
        >
            {{ $slot }}
        </div>
    </div>
</div>
