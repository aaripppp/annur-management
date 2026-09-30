<tr class="border-b border-outline-variant bg-surface-container-low">
    <th rowspan="3" class="sticky left-0 z-30 w-14 min-w-14 border-r border-outline-variant bg-surface-container-low px-3 py-3 text-center">No.</th>
    <th rowspan="3" class="sticky left-14 z-30 w-56 min-w-56 border-r-2 border-outline bg-surface-container-low px-4 py-3">Nama Siswa</th>
    @if($recapMonthlyCount > 0)
        <th colspan="{{ 13 * $recapMonthlyCount + 1 }}" class="{{ $recapMonthlyIsLastGroup ? '' : 'border-r-2 border-outline' }} px-4 py-3 text-center text-primary">Bulanan</th>
    @endif
    @if($recapYearlyCount > 0)
        <th colspan="{{ 3 * $recapYearlyCount }}" class="border-r-2 border-outline px-4 py-3 text-center text-primary">Tahunan</th>
    @endif
    @if($recapOneTimeCount > 0)
        <th colspan="{{ 3 * $recapOneTimeCount }}" class="px-4 py-3 text-center text-primary">Sekali Bayar</th>
    @endif
</tr>
<tr class="border-b border-outline-variant bg-surface-container-low">
    @if($recapMonthlyCount > 0)
        <th colspan="{{ $recapMonthlyCount + 1 }}" class="border-r-2 border-outline px-3 py-3 text-center">Ringkasan Tagihan</th>
        @foreach($classRecapReport['months'] as $month)
            <th colspan="{{ $recapMonthlyCount }}" class="{{ $recapMonthlyIsLastGroup ? '' : 'border-r border-outline-variant' }} px-3 py-3 text-center">{{ $month['label'] }} {{ $month['year'] }}</th>
        @endforeach
    @endif
    @foreach($recapYearlyTypes as $yearlyType)
        @php($yearlyBorder = ! $loop->last ? 'border-r border-outline-variant' : ($recapOneTimeCount > 0 ? 'border-r-2 border-outline' : ''))
        <th colspan="3" class="{{ $yearlyBorder }} px-3 py-3 text-center">{{ $yearlyType['name'] }}</th>
    @endforeach
    @foreach($recapOneTimeTypes as $oneTimeType)
        <th colspan="3" class="px-3 py-3 text-center">{{ $oneTimeType['name'] }}</th>
    @endforeach
</tr>
<tr class="border-b-2 border-primary bg-surface-container-low text-label-sm">
    @if($recapMonthlyCount > 0)
        @foreach($recapMonthlyTypes as $type)
            <th class="min-w-28 border-r border-outline-variant whitespace-nowrap px-3 py-3 text-center">{{ $type['name'] }}</th>
        @endforeach
        <th class="min-w-28 {{ $recapMonthlyIsLastGroup ? '' : 'border-r-2 border-outline' }} whitespace-nowrap px-3 py-3 text-center">Total</th>
        @foreach($classRecapReport['months'] as $month)
            @foreach($recapMonthlyTypes as $type)
                <th class="min-w-28 {{ $recapMonthlyIsLastGroup && $type['id'] === $recapLastMonthlyId ? '' : 'border-r border-outline-variant' }} whitespace-nowrap px-3 py-3 text-center">{{ $type['name'] }}</th>
            @endforeach
        @endforeach
    @endif
    @foreach($recapYearlyTypes as $yearlyType)
        @php($yearlySisaBorder = ! $loop->last ? 'border-r border-outline-variant' : ($recapOneTimeCount > 0 ? 'border-r-2 border-outline' : ''))
        <th class="min-w-32 border-r border-outline-variant whitespace-nowrap px-3 py-3 text-center">Tagihan</th>
        <th class="min-w-32 border-r border-outline-variant whitespace-nowrap px-3 py-3 text-center">Terbayar</th>
        <th class="min-w-32 {{ $yearlySisaBorder }} whitespace-nowrap px-3 py-3 text-center">Sisa</th>
    @endforeach
    @foreach($recapOneTimeTypes as $oneTimeType)
        <th class="min-w-32 border-r border-outline-variant whitespace-nowrap px-3 py-3 text-center">Tagihan</th>
        <th class="min-w-32 border-r border-outline-variant whitespace-nowrap px-3 py-3 text-center">Terbayar</th>
        <th class="min-w-32 {{ $loop->last ? '' : 'border-r border-outline-variant' }} whitespace-nowrap px-3 py-3 text-center">Sisa</th>
    @endforeach
</tr>
