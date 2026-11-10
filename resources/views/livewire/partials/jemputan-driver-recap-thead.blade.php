<tr class="border-b border-outline-variant bg-surface-container-low">
    <th rowspan="2" class="sticky left-0 z-40 w-14 min-w-14 whitespace-nowrap border-r border-outline-variant bg-surface-container-low px-3 py-2.5 text-center">No.</th>
    <th rowspan="2" class="sticky left-14 z-40 w-80 min-w-80 whitespace-nowrap border-r-2 border-outline bg-surface-container-low px-4 py-2.5">Nama Siswa</th>
    <th rowspan="2" class="min-w-40 whitespace-nowrap border-r border-outline-variant px-4 py-2.5">Kelas</th>
    <th rowspan="2" class="min-w-40 whitespace-nowrap border-r-2 border-outline px-4 py-2.5 text-center">Tarif Jemputan</th>
    @foreach($report['months'] as $month)
        <th colspan="3" class="whitespace-nowrap border-r-2 border-outline px-4 py-2.5 text-center text-primary">{{ $month['label'] }}</th>
    @endforeach
</tr>
<tr class="border-b-2 border-primary bg-surface-container-low text-label-sm">
    @foreach($report['months'] as $month)
        <th class="min-w-36 whitespace-nowrap border-r border-outline-variant px-3 py-2.5 text-center">Nominal</th>
        <th class="min-w-32 whitespace-nowrap border-r border-outline-variant px-3 py-2.5 text-center">Tanggal</th>
        <th class="min-w-36 whitespace-nowrap border-r-2 border-outline px-3 py-2.5 text-center">Bank</th>
    @endforeach
</tr>
