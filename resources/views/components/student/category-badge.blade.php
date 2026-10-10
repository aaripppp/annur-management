@props(['category'])

@php
    $colorClasses = match ($category->code) {
        'anak_guru', 'anak_pegawai' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
        'anak_yatim' => 'bg-green-50 text-green-800 border-green-200',
        'beasiswa' => 'bg-blue-50 text-blue-800 border-blue-200',
        default => 'bg-surface-container-high text-on-surface-variant border-outline-variant',
    };
@endphp

<span
    data-category-code="{{ $category->code }}"
    {{ $attributes->class(['inline-flex items-center px-2 py-0.5 rounded-full border text-label-sm font-label-sm whitespace-nowrap', $colorClasses]) }}
>
    {{ $category->name }}
</span>
