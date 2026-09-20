@props(['value' => null, 'for' => null, 'required' => false])

<label
    for="{{ $for }}"
    class="block text-body-sm font-label-md text-on-surface-variant mb-1"
    @if($required) aria-required="true" @endif
>
    {{ $value ?? $slot }}
    @if($required)
        <span class="text-error">*</span>
    @endif
</label>
