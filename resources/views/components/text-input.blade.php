@props(['disabled' => false, 'type' => 'text'])

<input
    type="{{ $type }}"
    @disabled($disabled)
    {!! $attributes->merge([
        'class' => 'block w-full px-4 py-2.5 h-[44px] border border-outline-variant rounded-lg bg-surface-container-lowest text-on-surface text-body-md font-body-md placeholder:text-outline focus:ring-2 focus:ring-secondary focus:border-secondary transition-colors'
    ]) !!}
>
