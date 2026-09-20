@props(['disabled' => false])

<button
    @disabled($disabled)
    {!! $attributes->merge([
        'class' => 'inline-flex items-center justify-center px-5 py-2.5 h-[44px] bg-surface-container text-on-surface font-label-md border border-outline-variant rounded-lg hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-outline focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed'
    ]) !!}
>
    {{ $slot }}
</button>
