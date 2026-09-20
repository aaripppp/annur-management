@props(['name'])

<svg {{ $attributes->class(['w-5 h-5'])->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('school')
            <path d="M3 8 12 3l9 5-9 5-9-5Z" stroke-linejoin="round" />
            <path d="M7 11v4.5c2.8 2 7.2 2 10 0V11M21 8v6" stroke-linecap="round" stroke-linejoin="round" />
            @break
        @case('person')
            <circle cx="12" cy="8" r="3" />
            <path d="M5.5 20c.7-4 3-6 6.5-6s5.8 2 6.5 6" stroke-linecap="round" />
            @break
        @case('bank')
            <path d="m3 9 9-5 9 5H3Z" stroke-linejoin="round" />
            <path d="M5 10v7m4-7v7m6-7v7m4-7v7M3 20h18M2 17h20" stroke-linecap="round" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M8 3v4m8-4v4M3 10h18" stroke-linecap="round" />
            @break
        @case('cancel')
            <circle cx="12" cy="12" r="9" />
            <path d="m9 9 6 6m0-6-6 6" stroke-linecap="round" />
            @break
    @endswitch
</svg>
