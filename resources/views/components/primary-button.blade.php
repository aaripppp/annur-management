@props(['disabled' => false])

<button
    @disabled($disabled)
    {!! $attributes->merge([
        'class' => 'inline-flex items-center justify-center px-5 py-2.5 h-[44px] bg-secondary text-on-secondary font-label-md rounded-lg shadow-sm hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed'
    ]) !!}
>
    {{ $slot }}
</button>
