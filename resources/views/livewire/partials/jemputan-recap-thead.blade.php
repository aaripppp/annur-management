<tr class="border-b border-outline-variant bg-surface-container-low">
    <th rowspan="2" class="sticky left-0 z-30 w-14 min-w-14 border-r border-outline-variant bg-surface-container-low px-3 py-3 text-center">No.</th>
    <th rowspan="2" class="sticky left-14 z-30 w-56 min-w-56 border-r-2 border-outline bg-surface-container-low px-4 py-3">Nama Siswa</th>
    <th colspan="3" class="border-r-2 border-outline px-4 py-3 text-center text-primary">Ringkasan Tagihan</th>
    <th colspan="12" class="border-r-2 border-outline px-4 py-3 text-center text-primary">Bulanan</th>
    <th class="px-4 py-3 text-center text-primary">Sekali Bayar</th>
</tr>
<tr class="border-b-2 border-primary bg-surface-container-low">
    <th class="min-w-32 border-r border-outline-variant px-3 py-3 text-center">Jemputan</th>
    <th class="min-w-32 border-r border-outline-variant px-3 py-3 text-center">Adm Jemputan</th>
    <th class="min-w-32 border-r-2 border-outline px-3 py-3 text-center">Total</th>
    @foreach($classRecapReport['months'] as $month)
        <th class="min-w-28 border-r border-outline-variant px-3 py-3 text-center">{{ $month['label'] }} {{ $month['year'] }}</th>
    @endforeach
    <th class="min-w-32 px-3 py-3 text-center">Adm Jemputan</th>
</tr>
