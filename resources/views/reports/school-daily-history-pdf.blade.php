<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Riwayat Transaksi Harian Sekolah</title>
    <style>
        @page { size: 216mm 330mm; margin: 12mm 11mm 13mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: "DejaVu Sans", sans-serif; font-size: 9px; line-height: 1.25; }
        .document-header { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .document-header td { vertical-align: middle; }
        .logo-cell { width: 25%; }
        .logo { width: auto; height: 52px; }
        .title-cell { width: 50%; text-align: center; }
        .title { font-size: 14px; font-weight: bold; color: #0f2b46; }
        .title-note { margin-top: 2px; font-size: 9px; font-weight: normal; }
        .header-right { width: 25%; }
        .meta { width: 100%; margin: 0 0 8px; border-collapse: collapse; }
        .meta td { padding: 1px 0; }
        .meta-label { width: 110px; }
        .meta-separator { width: 12px; text-align: center; }
        .report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .report-table th, .report-table td { border: .6px solid #000; padding: 3px 4px; vertical-align: middle; }
        .report-table th { font-weight: bold; text-align: center; background: #f4f7fb; }
        .col-no { width: 4%; text-align: center; }
        .col-receipt { width: 16%; }
        .col-date { width: 10%; text-align: center; }
        .col-name { width: 26%; }
        .col-class { width: 10%; text-align: center; }
        .col-payment { width: 20%; }
        .col-amount { width: 14%; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .report-table th.col-amount { text-align: center; }
        .section-cell { padding: 0; }
        .section-cell table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .section-cell table td { border: none; padding: 4px 4px; vertical-align: middle; }
        .section-label { text-align: left; width: 60%; }
        .section-total { text-align: right; width: 40%; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .bank-header { font-weight: bold; background: #dbe7f5; }
        .category-header { font-weight: bold; background: #eef3f9; }
        .grand-total { font-weight: bold; background: #0f2b46; color: #ffffff; }
        .empty-row td { font-style: italic; color: #666; text-align: center; padding: 6px; }
        .group-spacer td { border: none; height: 12px; line-height: 12px; padding: 0; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="document-header">
        <tr>
            <td class="logo-cell"><img src="{{ public_path('images/annur_logo2.png') }}" alt="Annur" class="logo"></td>
            <td class="title-cell">
                <div class="title">LAPORAN RIWAYAT TRANSAKSI HARIAN</div>
                <div class="title-note">KEUANGAN SEKOLAH<br>(DAILY TRANSACTION HISTORY)</div>
            </td>
            <td class="header-right"></td>
        </tr>
    </table>

    <table class="meta">
        <tr><td class="meta-label">{{ $document['period_title'] }}</td><td class="meta-separator">:</td><td>{{ $document['period_label'] }}</td></tr>
        <tr><td class="meta-label">Unit</td><td class="meta-separator">:</td><td>{{ $document['unit'] }}</td></tr>
        @if($document['jenjang'] !== null)
        <tr><td class="meta-label">Jenjang</td><td class="meta-separator">:</td><td>{{ $document['jenjang'] }}</td></tr>
        @endif
        <tr><td class="meta-label">Dicetak Oleh</td><td class="meta-separator">:</td><td>{{ $document['printed_by'] }}</td></tr>
        <tr><td class="meta-label">Tanggal Cetak</td><td class="meta-separator">:</td><td>{{ $document['printed_at'] }}</td></tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-receipt">No. Kwitansi</th>
                <th class="col-date">Tanggal</th>
                <th class="col-name">Nama Siswa</th>
                <th class="col-class">Kelas</th>
                <th class="col-payment">Pembayaran</th>
                <th class="col-amount">Nominal</th>
            </tr>
        </thead>
        @foreach($history['groups'] as $group)
        @php($markerCount = 0)
        <tbody>
            <tr class="bank-header">
                <td class="section-cell" colspan="7">
                    <table>
                        <tr>
                            <td class="section-label">{{ $group['header'] }}</td>
                            <td class="section-total">{{ $group['is_cash'] ? 'Total Tunai' : 'Total Bank' }}: {{ $group['total'] > 0 ? 'Rp '.number_format($group['total'], 0, ',', '.') : '' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            @if($group['empty'])
            <tr class="empty-row">
                <td colspan="7">{{ $group['is_cash'] ? 'Tidak ada transaksi tunai pada periode ini.' : 'Tidak ada transaksi pada bank ini.' }}</td>
            </tr>
            @else
                @foreach($group['categories'] as $category)
                @php($marker = $markerCount < 26 ? chr(65 + $markerCount) : '')
                @php($markerCount++)
                <tr class="category-header">
                    <td class="section-cell" colspan="7">
                        <table>
                            <tr>
                                <td class="section-label">{{ $marker !== '' ? $marker.'. ' : '' }}{{ $category['name'] }}</td>
                                <td class="section-total">Total {{ $category['name'] }} ({{ $group['is_cash'] ? 'Tunai' : $group['bank_name'] }}): Rp {{ number_format($category['total'], 0, ',', '.') }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @php($i = 0)
                @foreach($category['rows'] as $row)
                <tr>
                    <td class="col-no">{{ ++$i }}</td>
                    <td>{{ $row['receipt_number'] }}</td>
                    <td class="col-date">{{ $row['recorded_at']->format('d/m/Y') }}</td>
                    <td>{{ $row['student_name'] }}</td>
                    <td class="col-class">{{ $row['class_name'] }}</td>
                    <td>{{ $row['category_name'] }}</td>
                    <td class="col-amount">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
                @endforeach
            @endif
            @if(! $loop->last)
            <tr class="group-spacer">
                <td colspan="7">&nbsp;</td>
            </tr>
            @endif
        </tbody>
        @endforeach
        <tfoot>
            <tr class="grand-total">
                <td class="section-cell" colspan="7">
                    <table>
                        <tr>
                            <td class="section-label">GRAND TOTAL</td>
                            <td class="section-total">Rp {{ number_format($history['grand_total'], 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>