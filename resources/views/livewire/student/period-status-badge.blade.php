@php
    $isPaid = $status === \App\Models\StudentBill::STATUS_PAID;
    $isPartial = $status === \App\Models\StudentBill::STATUS_PARTIAL;
@endphp
<span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-label-sm font-label-sm whitespace-nowrap {{ $isPaid ? 'bg-tertiary-fixed text-on-tertiary-fixed' : ($isPartial ? 'bg-surface-container-highest text-on-surface-variant' : 'bg-error-container text-on-error-container') }}">
    @if ($isPaid)
        <span class="material-symbols-outlined text-[14px] leading-none">check_circle</span>
        Lunas
    @elseif ($isPartial)
        <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
        Sebagian
    @else
        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
        Belum Lunas
    @endif
</span>
